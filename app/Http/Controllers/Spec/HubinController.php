<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Actions\Placement\ActivatePlacementAction;
use App\Enums\PlacementStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PartnerAgreement;
use App\Models\PartnerContact;
use App\Models\PartnerEvaluation;
use App\Models\PartnerQuota;
use App\Models\PartnerRequest;
use App\Models\PklPeriod;
use App\Models\Placement;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherInternship;
use App\Models\UkkSession;
use App\Models\VisitReport;
use App\Support\Notify;
use App\Support\PublicToken;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hubin Partner Hub: direktori & profil 360 mitra (H01-H03), arsip MoU/PKS + pengingat (H04, H12), kuota (H05),
 * penempatan & pemetaan pembimbing massal (H06-H07), undangan PIC (H08, H14, H16), linimasa (H10),
 * evaluasi (H11), pusat kendali (H13), arsip tahunan (H17), dan Peta DUDI opt-in agregat (H18).
 * Data mitra milik masing-masing sekolah; tidak pernah dibagikan kecuali agregat anonim yang opt-in.
 */
final class HubinController extends Controller
{
    public const DUDI_MIN_SCHOOLS = 2;

    public const DUDI_MIN_REVIEWS = 5;

    public function index(Request $request): View
    {
        $period = PklPeriod::query()->where('is_active', true)->latest('start_date')->first();
        $companies = Company::query()->withCount(['placements as active_placements_count' => fn ($q) => $q->where('status', PlacementStatus::Active)])
            ->when($request->query('status'), fn ($q, $s) => $q->where('partnership_status', $s))
            ->when($request->query('q'), fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->orderBy('name')->get();
        $today = now($request->user()->school->timezone)->toDateString();
        $quotas = $period ? PartnerQuota::query()->with('company')->where('pkl_period_id', $period->id)->get() : collect();
        $filled = $period ? Placement::query()->where('pkl_period_id', $period->id)->whereIn('status', [PlacementStatus::Active, PlacementStatus::Confirmed, PlacementStatus::Offered])->get()->groupBy('company_id')->map->count() : collect();

        return view('spec.hubin.index', [
            'period' => $period,
            'companies' => $companies,
            'statusCounts' => Company::query()->selectRaw('partnership_status, count(*) as c')->groupBy('partnership_status')->pluck('c', 'partnership_status'),
            'quotas' => $quotas,
            'filled' => $filled,
            'reminders' => $this->reminders($today),
            'duplicates' => $companies->groupBy(fn ($c) => Str::slug(preg_replace('/\b(pt|cv|tbk|ud)\b\.?/i', '', $c->name).' '.$c->city))->filter(fn ($g) => $g->count() > 1),
            'distribution' => Placement::query()->where('status', PlacementStatus::Active)->join('students', 'students.id', '=', 'placements.student_id')
                ->selectRaw('students.major, count(*) as c')->groupBy('students.major')->pluck('c', 'students.major'),
            'internships' => TeacherInternship::query()->where('status', 'active')->count(),
        ]);
    }

    public function show(Request $request, Company $company): View
    {
        $company->load(['contacts', 'agreements', 'quotas.pklPeriod']);

        return view('spec.hubin.show', [
            'company' => $company,
            'placements' => Placement::query()->with(['student.user', 'pklPeriod', 'teacher.user'])->where('company_id', $company->id)->latest()->get(),
            'visits' => VisitReport::query()->with('author')->where('company_id', $company->id)->latest('visit_date')->get(),
            'internships' => TeacherInternship::query()->with('teacher.user')->where('company_id', $company->id)->get(),
            'evaluations' => PartnerEvaluation::query()->with('pklPeriod')->where('company_id', $company->id)->latest()->get(),
            'requests' => PartnerRequest::query()->where('company_id', $company->id)->latest()->get(),
            'periods' => PklPeriod::query()->latest('start_date')->get(),
            'timeline' => $this->timeline($company),
        ]);
    }

    public function updateStatus(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['partnership_status' => ['required', 'in:prospect,active,paused,archived'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $company->update([...$data, 'is_active' => $data['partnership_status'] !== 'archived', 'relation_manager_user_id' => $request->user()->id]);
        AuditLog::record('partner.status_changed', $company, ['status' => $data['partnership_status']]);

        return back()->with('status', 'Status kemitraan diperbarui.');
    }

    public function storeContact(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'position' => ['nullable', 'string', 'max:120'], 'division' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:24'], 'email' => ['nullable', 'email', 'max:150'], 'kind' => ['required', 'in:pic,mentor,examiner'],
            'valid_until' => ['nullable', 'date'], 'consent_note' => ['nullable', 'string', 'max:300'],
        ]);
        PartnerContact::query()->create([...$data, 'school_id' => $company->school_id, 'company_id' => $company->id, 'valid_from' => now()->toDateString()]);

        return back()->with('status', 'Kontak mitra tersimpan.');
    }

    public function storeAgreement(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:mou,pks,acceptance'], 'title' => ['required', 'string', 'max:200'], 'reference_no' => ['nullable', 'string', 'max:120'],
            'signed_on' => ['nullable', 'date'], 'expires_on' => ['nullable', 'date', 'after_or_equal:signed_on'], 'pic_name' => ['nullable', 'string', 'max:120'],
            'reminder_days' => ['required', 'integer', 'min:7', 'max:365'], 'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $path = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $mime = $file->getMimeType();
            $ext = $mime === 'application/pdf' ? 'pdf' : (array_search($mime, \App\Support\MediaStorage::IMAGE_TYPES, true) ?: null);
            if ($ext !== null) {
                $path = sprintf('schools/%s/agreements/%s/%s.%s', $company->school_id, now()->format('Y-m'), Str::uuid(), $ext);
                Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));
            }
        }
        $agreement = PartnerAgreement::query()->create([...collect($data)->except('file')->all(), 'school_id' => $company->school_id, 'company_id' => $company->id, 'file_path' => $path,
            'status' => ! empty($data['expires_on']) && $data['expires_on'] < now()->toDateString() ? 'expired' : 'active']);
        AuditLog::record('partner.agreement_added', $agreement, ['company' => $company->name, 'kind' => $data['kind']]);

        return back()->with('status', 'Dokumen kerja sama tersimpan sebagai arsip. Menyimpan dokumen tidak berarti memverifikasi keabsahan hukumnya.');
    }

    public function storeQuota(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'pkl_period_id' => ['nullable', 'uuid'], 'division' => ['nullable', 'string', 'max:120'], 'capacity' => ['required', 'integer', 'min:0', 'max:1000'],
            'source' => ['required', 'in:phone,letter,email,form,meeting'], 'confirmed' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:300'],
        ]);
        if (! empty($data['pkl_period_id'])) {
            PklPeriod::query()->findOrFail($data['pkl_period_id']);
        }
        PartnerQuota::query()->create([...collect($data)->except('confirmed')->all(), 'school_id' => $company->school_id, 'company_id' => $company->id, 'confirmed_at' => $request->boolean('confirmed') ? now() : null]);

        return back()->with('status', 'Kuota tersimpan bersama sumber dan tanggal konfirmasinya.');
    }

    public function storeRequest(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', 'in:contact_quota,feedback'], 'days' => ['required', 'integer', 'min:3', 'max:60']]);
        [$token, $hash] = PublicToken::make(40);
        PartnerRequest::query()->create(['school_id' => $company->school_id, 'company_id' => $company->id, 'kind' => $data['kind'], 'token_hash' => $hash, 'expires_at' => now()->addDays((int) $data['days'])]);
        $url = route('partner.form', $token);
        $pic = $company->contacts()->where('kind', 'pic')->where('is_active', true)->first();
        $message = 'Yth. '.($pic?->name ?? $company->contact_person_name ?? 'Bapak/Ibu').", mohon bantu ".($data['kind'] === 'feedback' ? 'mengisi masukan keterampilan untuk sekolah' : 'mengonfirmasi kontak dan kuota PKL')." melalui tautan berikut: {$url}";

        return back()->with('status', 'Tautan formulir mitra dibuat. Penerima tidak mendapat akses ke data sekolah lain.')
            ->with('magic_link', ['url' => $url, 'name' => $company->name, 'wa' => Notify::waShare($pic?->phone ?? $company->contact_person_phone, $message)]);
    }

    public function reviewRequest(Request $request, PartnerRequest $partnerRequest): RedirectResponse
    {
        $decision = $request->validate(['decision' => ['required', 'in:accepted,rejected']])['decision'];
        abort_unless($partnerRequest->status === 'responded', 422, 'Belum ada tanggapan untuk ditinjau.');
        if ($decision === 'accepted' && $partnerRequest->kind === 'contact_quota') {
            $r = $partnerRequest->response ?? [];
            if (! empty($r['pic_name'])) {
                PartnerContact::query()->create(['school_id' => $partnerRequest->school_id, 'company_id' => $partnerRequest->company_id, 'name' => $r['pic_name'], 'position' => $r['pic_position'] ?? null,
                    'phone' => $r['pic_phone'] ?? null, 'email' => $r['pic_email'] ?? null, 'kind' => 'pic', 'valid_from' => now()->toDateString(), 'consent_note' => 'Dikonfirmasi lewat formulir mitra']);
            }
            if (isset($r['capacity']) && is_numeric($r['capacity'])) {
                PartnerQuota::query()->create(['school_id' => $partnerRequest->school_id, 'company_id' => $partnerRequest->company_id, 'pkl_period_id' => PklPeriod::query()->where('is_active', true)->latest('start_date')->value('id'),
                    'division' => $r['division'] ?? null, 'capacity' => (int) $r['capacity'], 'source' => 'form', 'confirmed_at' => $partnerRequest->responded_at, 'notes' => 'Dari formulir mitra oleh '.$partnerRequest->responder_name]);
            }
        }
        $partnerRequest->update(['status' => $decision, 'reviewed_by_user_id' => $request->user()->id, 'reviewed_at' => now()]);

        return back()->with('status', $decision === 'accepted' ? 'Tanggapan mitra diterima dan disimpan.' : 'Tanggapan mitra tidak dipakai.');
    }

    public function storeEvaluation(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'pkl_period_id' => ['nullable', 'uuid'], 'evaluator_role' => ['required', 'in:hubin,teacher,student,mentor'],
            'scores' => ['required', 'array'], 'scores.*' => ['nullable', 'integer', 'min:1', 'max:5'], 'comment' => ['nullable', 'string', 'max:2000'], 'recommendation' => ['nullable', 'string', 'max:1000'],
        ]);
        PartnerEvaluation::query()->create([...$data, 'school_id' => $company->school_id, 'company_id' => $company->id, 'evaluator_user_id' => $request->user()->id]);

        return back()->with('status', 'Evaluasi kemitraan tersimpan untuk peninjauan internal. Tidak dipublikasikan sebagai rating.');
    }

    public function placements(): View
    {
        $period = PklPeriod::query()->where('is_active', true)->latest('start_date')->first();

        return view('spec.hubin.placements', [
            'period' => $period,
            'periods' => PklPeriod::query()->where('is_active', true)->latest('start_date')->get(),
            'students' => Student::query()->with('user')->whereDoesntHave('placements', fn ($q) => $q->where('status', PlacementStatus::Active)->when($period, fn ($w) => $w->where('pkl_period_id', $period->id)))->orderBy('class_name')->get(),
            'companies' => Company::query()->where('partnership_status', 'active')->orderBy('name')->get(),
            'teachers' => Teacher::query()->with('user')->withCount(['placements as load' => fn ($q) => $q->where('status', PlacementStatus::Active)])->get(),
            'quotas' => $period ? PartnerQuota::query()->where('pkl_period_id', $period->id)->get()->groupBy('company_id')->map->sum('capacity') : collect(),
            'filled' => $period ? Placement::query()->where('pkl_period_id', $period->id)->where('status', PlacementStatus::Active)->get()->groupBy('company_id')->map->count() : collect(),
            'active' => Placement::query()->with(['student.user', 'company', 'teacher.user'])->where('status', PlacementStatus::Active)->get(),
        ]);
    }

    public function bulkPlace(Request $request, ActivatePlacementAction $activate): RedirectResponse
    {
        $data = $request->validate([
            'pkl_period_id' => ['required', 'uuid'], 'company_id' => ['required', 'uuid'], 'teacher_id' => ['required', 'uuid'],
            'student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['uuid'], 'department_divisi' => ['nullable', 'string', 'max:100'],
            'work_start_time' => ['required', 'date_format:H:i'], 'work_end_time' => ['required', 'date_format:H:i'],
        ]);
        $period = PklPeriod::query()->findOrFail($data['pkl_period_id']);
        $company = Company::query()->findOrFail($data['company_id']);
        Teacher::query()->findOrFail($data['teacher_id']);
        $quota = PartnerQuota::query()->where('company_id', $company->id)->where('pkl_period_id', $period->id)->sum('capacity');
        $filled = Placement::query()->where('company_id', $company->id)->where('pkl_period_id', $period->id)->where('status', PlacementStatus::Active)->count();
        $warning = $quota > 0 && $filled + count($data['student_ids']) > $quota ? " Perhatian: melebihi kuota terkonfirmasi ({$quota})." : ($quota === 0 ? ' Perhatian: belum ada kuota terkonfirmasi untuk mitra ini.' : '');

        $ok = 0;
        $errors = [];
        foreach (Student::query()->with('user')->whereIn('id', $data['student_ids'])->get() as $student) {
            try {
                $activate->handle($request->user()->school, new Placement([
                    'pkl_period_id' => $period->id, 'student_id' => $student->id, 'company_id' => $company->id, 'teacher_id' => $data['teacher_id'],
                    'department_divisi' => $data['department_divisi'] ?? null, 'work_start_time' => $data['work_start_time'].':00', 'work_end_time' => $data['work_end_time'].':00',
                ]));
                $ok++;
            } catch (ValidationException $e) {
                $errors[] = $student->user->name.': '.collect($e->errors())->flatten()->first();
            }
        }
        AuditLog::record('hubin.bulk_placed', $company, ['count' => $ok]);

        return back()->with('status', "{$ok} siswa ditempatkan di {$company->name}.{$warning}")->with('import_errors', $errors);
    }

    public function bulkAssign(Request $request): RedirectResponse
    {
        $data = $request->validate(['teacher_id' => ['required', 'uuid'], 'placement_ids' => ['required', 'array', 'min:1'], 'placement_ids.*' => ['uuid']]);
        $teacher = Teacher::query()->findOrFail($data['teacher_id']);
        $load = Placement::query()->where('teacher_id', $teacher->id)->where('status', PlacementStatus::Active)->count();
        $moving = Placement::query()->whereIn('id', $data['placement_ids'])->where('teacher_id', '!=', $teacher->id)->where('status', PlacementStatus::Active)->get();
        if ($load + $moving->count() > $teacher->max_mentees) {
            return back()->withErrors(['teacher_id' => "Beban {$teacher->user->name} akan menjadi ".($load + $moving->count())." siswa, melebihi batas {$teacher->max_mentees}."]);
        }
        // Keputusan review historis tetap atas nama guru lama (validated_by_teacher_id tidak diubah).
        foreach ($moving as $p) {
            $from = $p->teacher_id;
            $p->update(['teacher_id' => $teacher->id]);
            AuditLog::record('hubin.mentor_reassigned', $p, ['from' => $from, 'to' => $teacher->id]);
        }

        return back()->with('status', $moving->count()." penempatan dipindahkan ke {$teacher->user->name}.");
    }

    public function archive(Request $request): View
    {
        $year = $request->query('tahun') ?: PklPeriod::query()->latest('start_date')->value('academic_year');

        return view('spec.hubin.archive', ['year' => $year, 'years' => PklPeriod::query()->distinct()->pluck('academic_year'), 'rows' => $this->archiveRows($year)]);
    }

    public function archiveCsv(Request $request): StreamedResponse
    {
        $year = (string) $request->query('tahun');
        $rows = $this->archiveRows($year);

        return response()->streamDownload(function () use ($rows, $year): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tahun ajaran', 'Mitra', 'Status', 'Siswa PKL', 'Magang guru', 'Kunjungan', 'Dokumen aktif', 'Sesi UKK']);
            foreach ($rows as $r) {
                fputcsv($out, [$year, $r['company']->name, $r['company']->partnershipLabel(), $r['placements'], $r['internships'], $r['visits'], $r['agreements'], $r['ukk']]);
            }
            fclose($out);
        }, 'realisasi-kemitraan-'.Str::slug($year).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Peta DUDI (H18): agregat anonim lintas sekolah yang opt-in. Tanpa kontak, dokumen, atau nama siswa. */
    public function dudiMap(Request $request): View
    {
        $school = $request->user()->school;
        $rows = collect();
        if ($school->peta_dudi_opt_in) {
            $rows = TenantContext::runAsSuperAdmin(fn () => DB::table('placements as p')
                ->join('companies as c', 'c.id', '=', 'p.company_id')
                ->join('schools as s', 's.id', '=', 'p.school_id')
                ->where('s.peta_dudi_opt_in', true)
                ->whereIn('p.status', ['active', 'completed'])
                ->selectRaw("lower(regexp_replace(c.name, '\\s+', ' ', 'g')) as key, max(c.name) as name, max(c.industry_type) as sector, max(c.city) as city, count(distinct p.school_id) as schools, count(*) as students")
                ->groupBy('key')->get())
                ->filter(fn ($r) => $r->schools >= self::DUDI_MIN_SCHOOLS)
                ->map(function ($r) {
                    $reviews = TenantContext::runAsSuperAdmin(fn () => DB::table('partner_evaluations as e')->join('companies as c', 'c.id', '=', 'e.company_id')->join('schools as s', 's.id', '=', 'e.school_id')
                        ->where('s.peta_dudi_opt_in', true)->where('e.evaluator_role', 'student')->whereRaw("lower(regexp_replace(c.name, '\\s+', ' ', 'g')) = ?", [$r->key])->pluck('e.scores'));
                    $avg = $reviews->count() >= self::DUDI_MIN_REVIEWS ? round($reviews->map(fn ($s) => collect(json_decode((string) $s, true))->filter()->avg())->avg(), 1) : null;

                    return (object) [...(array) $r, 'reviews' => $reviews->count(), 'avg' => $avg];
                })
                ->when($request->query('q'), fn ($c, $q) => $c->filter(fn ($r) => str_contains(mb_strtolower($r->name.' '.$r->sector.' '.$r->city), mb_strtolower($q))))
                ->sortByDesc('students')->values();
        }

        return view('spec.hubin.dudi-map', ['school' => $school, 'rows' => $rows]);
    }

    public function toggleDudiOptIn(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $school->forceFill(['peta_dudi_opt_in' => ! $school->peta_dudi_opt_in])->save();
        AuditLog::record('hubin.dudi_opt_in', $school, ['opt_in' => $school->peta_dudi_opt_in], $school->id);

        return back()->with('status', $school->peta_dudi_opt_in ? 'Sekolah ikut Peta DUDI. Hanya agregat anonim yang dibagikan.' : 'Sekolah keluar dari Peta DUDI. Data sekolah tidak lagi dihitung.');
    }

    /** @return Collection<int, array{date:string, title:string, kind:string, source:string}> */
    private function timeline(Company $company): Collection
    {
        $events = collect();
        foreach (Placement::query()->with(['student.user', 'pklPeriod'])->where('company_id', $company->id)->get()->groupBy('pkl_period_id') as $items) {
            $events->push(['date' => $items->first()->pklPeriod->start_date->toDateString(), 'title' => $items->count().' siswa PKL periode '.$items->first()->pklPeriod->name, 'kind' => 'PKL', 'source' => 'Penempatan']);
        }
        foreach ($company->agreements as $a) {
            $events->push(['date' => ($a->signed_on ?? $a->created_at)->toDateString(), 'title' => strtoupper($a->kind).' '.$a->title.($a->expires_on ? ' (berlaku s.d. '.$a->expires_on->format('d/m/Y').')' : ''), 'kind' => 'Dokumen', 'source' => 'Arsip MoU/PKS']);
        }
        foreach (VisitReport::query()->where('company_id', $company->id)->get() as $v) {
            $events->push(['date' => $v->visit_date->toDateString(), 'title' => 'Kunjungan '.($v->kind === 'monitoring' ? 'monitoring' : 'kemitraan'), 'kind' => 'Kunjungan', 'source' => 'Laporan kunjungan']);
        }
        foreach (TeacherInternship::query()->with('teacher.user')->where('company_id', $company->id)->get() as $i) {
            $events->push(['date' => $i->start_date->toDateString(), 'title' => 'Magang guru: '.$i->teacher->user->name, 'kind' => 'Magang guru', 'source' => 'Program magang guru']);
        }
        foreach (UkkSession::query()->where('venue_company_id', $company->id)->get() as $u) {
            $events->push(['date' => $u->scheduled_at->toDateString(), 'title' => 'UKK '.$u->concentration.' di tempat mitra', 'kind' => 'UKK', 'source' => 'Sesi UKK']);
        }
        foreach (PartnerEvaluation::query()->where('company_id', $company->id)->get() as $e) {
            $events->push(['date' => $e->created_at->toDateString(), 'title' => 'Evaluasi kemitraan ('.$e->evaluator_role.')', 'kind' => 'Evaluasi', 'source' => 'Evaluasi internal']);
        }

        return $events->sortByDesc('date')->values();
    }

    /** Pengingat (H12): dokumen akan habis, kunjungan terjadwal, kuota belum dikonfirmasi, tindak lanjut kunjungan. */
    private function reminders(string $today): Collection
    {
        $soon = now()->addDays(90)->toDateString();
        $items = collect();
        foreach (PartnerAgreement::query()->with('company')->whereNotNull('expires_on')->whereDate('expires_on', '<=', $soon)->where('status', '!=', 'terminated')->get() as $a) {
            if ($a->expires_on->toDateString() <= now()->addDays($a->reminder_days)->toDateString()) {
                $items->push(['urgency' => $a->expires_on->toDateString() < $today ? 'tinggi' : 'sedang', 'text' => strtoupper($a->kind)." {$a->company->name} ".($a->expires_on->toDateString() < $today ? 'sudah berakhir' : 'berakhir').' '.$a->expires_on->format('d/m/Y'), 'company' => $a->company]);
            }
        }
        foreach (VisitReport::query()->with('company')->where('status', 'planned')->whereDate('visit_date', '>=', $today)->get() as $v) {
            $items->push(['urgency' => 'rendah', 'text' => 'Kunjungan terjadwal ke '.($v->company?->name ?? '-').' pada '.$v->visit_date->format('d/m/Y'), 'company' => $v->company]);
        }
        foreach (VisitReport::query()->with('company')->whereNotNull('follow_up_due')->whereDate('follow_up_due', '<', $today)->get() as $v) {
            $items->push(['urgency' => 'sedang', 'text' => 'Tindak lanjut kunjungan '.($v->company?->name ?? '-').' lewat tenggat: '.$v->follow_up, 'company' => $v->company]);
        }
        foreach (PartnerQuota::query()->with('company')->whereNull('confirmed_at')->get() as $q) {
            $items->push(['urgency' => 'rendah', 'text' => "Kuota {$q->company->name} ({$q->capacity}) belum dikonfirmasi", 'company' => $q->company]);
        }

        return $items->sortBy(fn ($i) => ['tinggi' => 0, 'sedang' => 1, 'rendah' => 2][$i['urgency']])->values();
    }

    private function archiveRows(string $year): Collection
    {
        $periodIds = PklPeriod::query()->where('academic_year', $year)->pluck('id');
        $periods = PklPeriod::query()->whereIn('id', $periodIds)->get();
        $from = $periods->min('start_date');
        $until = $periods->max('end_date');

        return Company::query()->orderBy('name')->get()->map(fn ($c) => [
            'company' => $c,
            'placements' => Placement::query()->where('company_id', $c->id)->whereIn('pkl_period_id', $periodIds)->count(),
            'internships' => $from ? TeacherInternship::query()->where('company_id', $c->id)->whereBetween('start_date', [$from, $until])->count() : 0,
            'visits' => $from ? VisitReport::query()->where('company_id', $c->id)->where('status', 'done')->whereBetween('visit_date', [$from, $until])->count() : 0,
            'agreements' => PartnerAgreement::query()->where('company_id', $c->id)->where('status', 'active')->count(),
            'ukk' => UkkSession::query()->where('venue_company_id', $c->id)->where('academic_year', $year)->count(),
        ])->filter(fn ($r) => $r['placements'] + $r['internships'] + $r['visits'] + $r['ukk'] > 0)->values();
    }
}
