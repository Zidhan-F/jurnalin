<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Onboarding\ImportStudentsAction;
use App\Actions\Onboarding\ImportTeachersAction;
use App\Actions\Placement\ActivatePlacementAction;
use App\Enums\JournalStatus;
use App\Enums\PlacementStatus;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Journal;
use App\Models\MentorSignoff;
use App\Models\Company;
use App\Models\PklPeriod;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\MediaStorage;
use App\Support\SpreadsheetHelper;
use App\Support\TenantContext;
use App\ValueObjects\AssessmentRubric;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class SchoolAdminController extends Controller
{
    /** Jurnal dianggap tertunda lama bila tanggalnya lebih tua dari sekian hari dan belum diperiksa guru. */
    private const STALE_JOURNAL_DAYS = 3;

    public function index(Request $request): View
    {
        $school = $request->user()->school;
        $activePeriod = PklPeriod::query()->where('is_active', true)->latest('start_date')->first();

        $placements = Placement::query()
            ->with(['student.user', 'company', 'teacher.user', 'pklPeriod', 'assessment'])
            ->latest()
            ->limit(25)
            ->get();

        $activePlacements = Placement::query()->where('status', PlacementStatus::Active)->count();
        $today = Carbon::now($school->timezone)->toDateString();

        // Pantauan selama PKL: hanya angka yang menuntun admin ke tindakan berikutnya.
        $monitor = $activePlacements === 0 ? null : [
            'checked_in_today' => Attendance::query()
                ->whereDate('attendance_date', $today)
                ->whereHas('placement', fn ($q) => $q->where('status', PlacementStatus::Active))
                ->distinct()
                ->count('placement_id'),
            'pending_journals' => Journal::query()->where('status', JournalStatus::Submitted)->count(),
            'stale_journals' => Journal::query()
                ->where('status', JournalStatus::Submitted)
                ->whereDate('journal_date', '<', Carbon::parse($today)->subDays(self::STALE_JOURNAL_DAYS)->toDateString())
                ->count(),
            'unplaced_students' => Student::query()
                ->whereDoesntHave('placements', fn ($q) => $q->where('status', '!=', PlacementStatus::Cancelled))
                ->count(),
        ];

        return view('admin.index', [
            'school' => $school,
            'activePeriod' => $activePeriod,
            'counts' => [
                'teachers' => Teacher::query()->count(),
                'students' => Student::query()->count(),
                'companies' => Company::query()->where('is_active', true)->count(),
                'active_placements' => $activePlacements,
            ],
            'monitor' => $monitor,
            'placements' => $placements,
        ]);
    }

    /** Jurnal berstatus menunggu validasi, dikelompokkan per guru pembimbing. */
    public function pendingJournals(Request $request): View
    {
        $school = $request->user()->school;
        $today = Carbon::now($school->timezone)->startOfDay();
        $staleBefore = $today->copy()->subDays(self::STALE_JOURNAL_DAYS);
        $onlyStale = $request->query('filter') === 'lama';

        $journals = Journal::query()
            ->with(['student.user', 'placement.company', 'placement.teacher.user'])
            ->where('status', JournalStatus::Submitted)
            ->when($onlyStale, fn ($q) => $q->whereDate('journal_date', '<', $staleBefore->toDateString()))
            ->orderBy('journal_date')
            ->get();

        $groups = $journals
            ->groupBy(fn (Journal $j) => $j->placement->teacher_id)
            ->map(function ($items) use ($today, $staleBefore) {
                return [
                    'teacher' => $items->first()->placement->teacher,
                    'journals' => $items,
                    'count' => $items->count(),
                    'stale' => $items->filter(fn (Journal $j) => $j->journal_date->lt($staleBefore))->count(),
                    'oldest_days' => (int) $items->first()->journal_date->copy()->startOfDay()->diffInDays($today),
                    'oldest_date' => $items->first()->journal_date,
                ];
            })
            ->sortBy([['stale', 'desc'], ['oldest_days', 'desc']])
            ->values();

        return view('admin.pending-journals', [
            'groups' => $groups,
            'total' => $journals->count(),
            'today' => $today,
            'onlyStale' => $onlyStale,
            'staleDays' => self::STALE_JOURNAL_DAYS,
        ]);
    }

    public function periods(): View
    {
        $periods = PklPeriod::query()
            ->withCount('placements')
            ->latest('start_date')
            ->get();

        return view('admin.periods', [
            'periods' => $periods,
            'defaultTemplate' => AssessmentRubric::defaultTemplate(),
        ]);
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'academic_year' => ['required', 'string', 'max:16'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            // Durasi PKL bebas diatur sekolah (1, 3, 6 bulan, 1 tahun, atau tanggal kustom).
            'min_required_days' => ['required', 'integer', 'min:1', 'max:366'],
            'industry_percentage' => ['required', 'integer', 'min:10', 'max:90'],
            'school_percentage' => ['required', 'integer', 'min:5', 'max:80'],
            'attendance_percentage' => ['required', 'integer', 'min:0', 'max:50'],
        ]);

        $periodDays = (int) Carbon::parse($validated['start_date'])->diffInDays(Carbon::parse($validated['end_date'])) + 1;
        if ((int) $validated['min_required_days'] > $periodDays) {
            return back()->withInput()->withErrors([
                'min_required_days' => "Target hari hadir ({$validated['min_required_days']} hari) melebihi lama periode ({$periodDays} hari). Kurangi target atau perpanjang tanggal selesai.",
            ]);
        }

        $template = $request->input('template_kind', 'tp') === 'classic' ? AssessmentRubric::defaultTemplate() : AssessmentRubric::tpTemplate();
        $template['weights'] = [
            'industry_percentage' => (int) $validated['industry_percentage'],
            'school_percentage' => (int) $validated['school_percentage'],
            'attendance_percentage' => (int) $validated['attendance_percentage'],
        ];

        try {
            AssessmentRubric::fromArray($template);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['industry_percentage' => $e->getMessage()]);
        }

        PklPeriod::query()->create([
            'name' => $validated['name'],
            'academic_year' => $validated['academic_year'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'min_required_days' => (int) $validated['min_required_days'],
            'assessment_template' => $template,
            'is_active' => true,
            'status' => 'active',
            'require_location' => $request->boolean('require_location', true),
        ]);

        return back()->with('status', "Periode PKL \"{$validated['name']}\" berhasil dibuat.");
    }

    public function togglePeriod(PklPeriod $period): RedirectResponse
    {
        $period->update(['is_active' => ! $period->is_active]);

        AuditLog::record($period->is_active ? 'period.activated' : 'period.archived', $period);
        $status = $period->is_active ? 'diaktifkan' : 'diarsipkan';

        return back()->with('status', "Periode {$period->name} {$status}.")->with('status_popup', true);
    }

    public function companies(Request $request): View
    {
        return view('admin.companies', [
            'school' => $request->user()->school,
            'companies' => Company::query()->withCount('placements')->orderBy('name')->get(),
        ]);
    }

    public function storeCompany(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'industry_type' => ['required', 'string', 'max:64'],
            'address' => ['required', 'string', 'max:300'],
            'city' => ['required', 'string', 'max:100'],
            'contact_person_name' => ['nullable', 'string', 'max:120'],
            'contact_person_phone' => ['nullable', 'string', 'max:24'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'geofence_radius_m' => ['nullable', 'integer', 'min:30', 'max:2000'],
        ]);

        Company::query()->create($validated);

        return back()->with('status', "Tempat PKL \"{$validated['name']}\" berhasil disimpan.");
    }

    public function teachers(): View
    {
        $teachers = Teacher::query()
            ->with('user')
            ->withCount(['placements' => fn ($q) => $q->where('status', PlacementStatus::Active)])
            ->get()
            ->sortBy(fn ($t) => $t->user->name);

        return view('admin.teachers', [
            'teachers' => $teachers,
        ]);
    }

    public function storeTeacher(Request $request, ImportTeachersAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:24'],
            'nip_nik' => ['nullable', 'string', 'max:32'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'max_mentees' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $error = $action->createOne($request->user()->school, $validated);
        if ($error !== null) {
            return back()->withInput()->withErrors(['email' => $error]);
        }

        return back()->with('status', "Guru {$validated['name']} ditambahkan (sandi awal: password123, wajib diganti saat login pertama).");
    }

    public function importTeachers(Request $request, ImportTeachersAction $action): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:4096'],
        ]);

        $file = $request->file('file');
        $result = $action->handle(
            $request->user()->school,
            $file->getRealPath(),
            $file->getClientOriginalExtension(),
        );

        return back()
            ->with('status', "Impor guru selesai: {$result['imported']} berhasil, {$result['skipped']} dilewati.")
            ->with('import_errors', $result['errors']);
    }

    public function downloadTeacherTemplate(): BinaryFileResponse
    {
        return $this->downloadTemplate('template-impor-guru-jurnalin.xlsx', [
            ['nip_nik', 'nama', 'email', 'no_wa', 'spesialisasi', 'maks_bimbingan'],
            ['198504122010012014', 'Dewi Lestari, S.Kom', 'dewi.lestari@sekolah.sch.id', '6281234567890', 'Rekayasa Perangkat Lunak', 20],
        ]);
    }

    public function students(Request $request): View
    {
        $school = $request->user()->school;
        $students = Student::query()
            ->with('user')
            ->orderBy('class_name')
            ->orderBy('nisn')
            ->get();

        return view('admin.students', [
            'school' => $school,
            'students' => $students,
        ]);
    }

    public function storeStudent(Request $request, ImportStudentsAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'nisn' => ['required', 'string', 'max:16'],
            'nis' => ['nullable', 'string', 'max:24'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:24'],
            'class_name' => ['required', 'string', 'max:64'],
            'major' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'in:L,P'],
            'parent_phone' => ['nullable', 'string', 'max:24'],
        ]);

        $error = $action->createOne($request->user()->school, $validated);
        if ($error !== null) {
            return back()->withInput()->withErrors(['nisn' => $error]);
        }

        return back()->with('status', "Siswa {$validated['name']} ditambahkan (sandi awal: password123, wajib diganti saat login pertama).");
    }

    public function importStudents(Request $request, ImportStudentsAction $action): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:4096'],
        ]);

        $file = $request->file('file');
        $result = $action->handle(
            $request->user()->school,
            $file->getRealPath(),
            $file->getClientOriginalExtension(),
        );

        return back()
            ->with('status', "Impor siswa selesai: {$result['imported']} berhasil, {$result['skipped']} dilewati.")
            ->with('import_errors', $result['errors']);
    }

    public function downloadStudentTemplate(): BinaryFileResponse
    {
        return $this->downloadTemplate('template-impor-siswa-jurnalin.xlsx', [
            ['nisn', 'nis', 'nama', 'email', 'no_wa', 'kelas', 'jurusan', 'jenis_kelamin', 'no_wa_ortu'],
            ['0081122334', '2425101', 'Rania Putri Maharani', 'rania.putri@siswa.sch.id', '6281298765432', 'XII RPL 1', 'Rekayasa Perangkat Lunak', 'P', '6281122334455'],
        ]);
    }

    public function placements(): View
    {
        return view('admin.placements', [
            'periods' => PklPeriod::query()->where('is_active', true)->orderByDesc('start_date')->get(),
            'students' => Student::query()->with('user')->orderBy('class_name')->get(),
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(),
            'teachers' => Teacher::query()->with('user')->get()->sortBy(fn ($t) => $t->user->name),
            'placements' => Placement::query()
                ->with(['pklPeriod', 'student.user', 'company', 'teacher.user'])
                ->latest()
                ->get(),
        ]);
    }

    public function storePlacement(Request $request, ActivatePlacementAction $activate): RedirectResponse
    {
        $validated = $request->validate([
            'pkl_period_id' => ['required', 'uuid'],
            'student_id' => ['required', 'uuid'],
            'company_id' => ['required', 'uuid'],
            'teacher_id' => ['required', 'uuid'],
            'department_divisi' => ['nullable', 'string', 'max:100'],
            'industry_mentor_name' => ['nullable', 'string', 'max:120'],
            'industry_mentor_phone' => ['nullable', 'string', 'max:24'],
            'work_start_time' => ['required', 'date_format:H:i'],
            'work_end_time' => ['required', 'date_format:H:i'],
        ]);

        // Verifikasi foreign key tetap berada dalam tenant sekolah aktif
        PklPeriod::query()->findOrFail($validated['pkl_period_id']);
        Student::query()->findOrFail($validated['student_id']);
        Company::query()->findOrFail($validated['company_id']);
        Teacher::query()->findOrFail($validated['teacher_id']);
        $activate->handle($request->user()->school, new Placement([
            ...$validated,
            'work_start_time' => $validated['work_start_time'].':00',
            'work_end_time' => $validated['work_end_time'].':00',
        ]));

        return back()->with('status', 'Penempatan PKL siswa berhasil disimpan.');
    }

    public function updatePlacementStatus(Request $request, Placement $placement, ActivatePlacementAction $activate): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,completed,cancelled'],
        ]);

        $next = PlacementStatus::from($validated['status']);
        if (! $placement->status->canTransitionTo($next)) {
            return back()->withErrors(['status' => "Status penempatan {$placement->status->label()} tidak dapat diubah menjadi {$next->label()}."]);
        }

        if ($next === PlacementStatus::Active) {
            $activate->handle($request->user()->school, $placement);
        } else {
            $placement->update(['status' => $next]);
        }
        AuditLog::record('placement.status_changed', $placement, ['status' => $next->value]);

        return back()->with('status', "Status penempatan diubah menjadi {$next->label()}.");
    }

    public function exportData(Request $request): BinaryFileResponse
    {
        $school = $request->user()->school;
        $tables = [
            'pengguna' => User::query()->where('school_id', $school->id)->get(['id', 'name', 'email', 'phone', 'role', 'is_active', 'last_login_at', 'created_at']),
            'guru' => Teacher::query()->get(),
            'siswa' => Student::query()->get(),
            'tempat_pkl' => Company::query()->get(),
            'periode' => PklPeriod::query()->get(),
            // Token tautan DUDI tidak ikut diekspor: itu kredensial, bukan data.
            'penempatan' => Placement::query()->get()->each->makeHidden(['mentor_link_hash', 'mentor_link_token']),
            'kehadiran' => Attendance::query()->get(),
            'jurnal' => Journal::query()->get(),
            'nilai' => Assessment::query()->get(),
            'tanda_tangan_dudi' => MentorSignoff::query()->get(),
            'log_aktivitas' => AuditLog::query()->get(),
        ];

        $path = tempnam(sys_get_temp_dir(), 'jurnalin-export');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($tables as $name => $rows) {
            $csv = fopen('php://temp', 'r+');
            $records = $rows->map(fn ($m) => $m->toArray())->all();
            if ($records !== []) {
                fputcsv($csv, array_keys($records[0]));
                foreach ($records as $record) {
                    fputcsv($csv, array_map(fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v, $record));
                }
            }
            rewind($csv);
            $zip->addFromString("{$name}.csv", stream_get_contents($csv));
            fclose($csv);
        }
        $zip->close();

        AuditLog::record('school.exported', $school, [], $school->id);

        return response()->download($path, 'jurnalin-ekspor-'.$school->npsn.'-'.now()->format('Ymd').'.zip')->deleteFileAfterSend();
    }

    public function requestDeletion(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $request->validate(['confirm_npsn' => ['required', 'in:'.$school->npsn]], [
            'confirm_npsn.in' => 'NPSN yang diketik tidak cocok. Penghapusan tidak diajukan.',
        ]);

        $school->update(['deletion_requested_at' => now()]);
        AuditLog::record('school.deletion_requested', $school, [], $school->id);

        return back()->with('status', 'Pengajuan penghapusan diterima. Data dihapus permanen dalam 30 hari. Unduh ekspor sebelum itu bila masih diperlukan.');
    }

    public function cancelDeletion(Request $request): RedirectResponse
    {
        $request->user()->school->update(['deletion_requested_at' => null]);

        return back()->with('status', 'Pengajuan penghapusan dibatalkan.');
    }

    public function activity(): View
    {
        return view('admin.activity', [
            'logs' => AuditLog::query()->with('user')->latest('created_at')->limit(200)->get(),
        ]);
    }

    public function settings(Request $request): View
    {
        return view('admin.settings', [
            'school' => $request->user()->school,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'address' => ['required', 'string', 'max:300'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
            'default_geofence_radius_m' => ['required', 'integer', 'min:30', 'max:2000'],
            'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'header_line_1' => ['nullable', 'string', 'max:180'],
            'header_line_2' => ['nullable', 'string', 'max:180'],
            'sub_header' => ['nullable', 'string', 'max:250'],
            'principal_name' => ['nullable', 'string', 'max:120'],
            'principal_nip' => ['nullable', 'string', 'max:40'],
            'coordinator_name' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'mentor_signoff_frequency' => ['required', 'in:daily,weekly,monthly'],
        ]);

        $logoPath = $school->logo_path;
        if ($request->hasFile('logo')) {
            $logoPath = MediaStorage::storeStudentUpload($school->id, 'branding', $request->file('logo')) ?? $logoPath;
        }

        $school->update([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'city' => $validated['city'],
            'province' => $validated['province'],
            'timezone' => $validated['timezone'],
            'default_geofence_radius_m' => (int) $validated['default_geofence_radius_m'],
            'late_tolerance_minutes' => (int) $validated['late_tolerance_minutes'],
            'require_attendance_photo' => $request->boolean('require_attendance_photo'),
            'require_industry_form_photo' => $request->boolean('require_industry_form_photo'),
            'mentor_signoff_frequency' => $validated['mentor_signoff_frequency'],
            'logo_path' => $logoPath,
            'letterhead_config' => [
                'header_line_1' => $validated['header_line_1'] ?? '',
                'header_line_2' => $validated['header_line_2'] ?? strtoupper($validated['name']),
                'sub_header' => $validated['sub_header'] ?? ($validated['address'].', '.$validated['city']),
                'principal_name' => $validated['principal_name'] ?? '',
                'principal_nip' => $validated['principal_nip'] ?? '',
                'coordinator_name' => $validated['coordinator_name'] ?? '',
            ],
        ]);

        return back()->with('status', 'Pengaturan sekolah dan kop surat PDF berhasil diperbarui.');
    }

    public function resetUserPassword(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageable($request, $user);

        $user->forceFill(['password' => 'password123', 'must_change_password' => true, 'remember_token' => null])->save();
        AuditLog::record('user.password_reset_by_admin', $user, ['name' => $user->name]);

        return back()->with('status', "Kata sandi {$user->name} direset ke sandi awal (password123). Pengguna wajib menggantinya saat login.");
    }

    public function editUser(Request $request, User $user): View
    {
        $this->ensureManageable($request, $user);

        return view('admin.user-edit', ['managed' => $user]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageable($request, $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:24'],
        ]);
        $validated['email'] = strtolower($validated['email']);

        // Email unik di seluruh Jurnalin, bukan hanya di sekolah ini.
        if ($validated['email'] !== $user->email && TenantContext::userEmailExists($validated['email'])) {
            return back()->withInput()->withErrors(['email' => 'Email ini sudah digunakan oleh akun lain.']);
        }

        $changed = array_keys(array_diff_assoc(array_map(fn ($v) => (string) $v, $validated), array_map(fn ($v) => (string) $v, $user->only(['name', 'email', 'phone']))));
        $user->update($validated);
        if ($changed !== []) {
            AuditLog::record('user.updated', $user, ['name' => $user->name, 'kolom' => $changed]);
        }

        return redirect($user->role === UserRole::Teacher ? '/admin/teachers' : '/admin/students')
            ->with('status', "Data {$user->name} diperbarui.");
    }

    public function toggleUserActive(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageable($request, $user);

        $user->forceFill(['is_active' => ! $user->is_active, 'remember_token' => null])->save();
        AuditLog::record($user->is_active ? 'user.activated' : 'user.deactivated', $user, ['name' => $user->name]);

        return back()->with('status', $user->is_active
            ? "Akun {$user->name} diaktifkan kembali."
            : "Akun {$user->name} dinonaktifkan.")->with('status_popup', true);
    }

    /** Admin sekolah hanya mengelola guru dan siswa sekolahnya sendiri (RLS + cek eksplisit sebagai lapis kedua). */
    private function ensureManageable(Request $request, User $user): void
    {
        abort_unless($user->school_id === $request->user()->school_id && $user->hasRole(UserRole::Teacher, UserRole::Student), 404);
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function downloadTemplate(string $downloadName, array $rows): BinaryFileResponse
    {
        // Berkas sementara unik per request: dua admin yang mengunduh bersamaan tidak saling menghapus.
        $path = tempnam(sys_get_temp_dir(), 'jurnalin-tpl');
        SpreadsheetHelper::writeXlsx($path, $rows);

        return response()->download($path, $downloadName)->deleteFileAfterSend();
    }
}
