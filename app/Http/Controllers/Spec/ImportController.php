<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Actions\Onboarding\ImportStudentsAction;
use App\Actions\Onboarding\ImportTeachersAction;
use App\Actions\Placement\ActivatePlacementAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\ImportLog;
use App\Models\PklPeriod;
use App\Models\Placement;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SpreadsheetHelper;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Impor data siswa, guru, dan penempatan dari template Excel atau ekspor Dapodik (P38).
 * Alur: unggah, validasi per baris + tandai duplikat, pratinjau, lalu proses. Data yang sudah ada tidak
 * pernah ditimpa. Berkas tidak disimpan; baris pratinjau hanya di sesi dan dihapus setelah diproses.
 */
final class ImportController extends Controller
{
    /** Pemetaan nama kolom Dapodik / variasi umum ke kolom template Jurnalin. */
    private const ALIASES = [
        'nama_peserta_didik' => 'nama', 'nama_lengkap' => 'nama', 'nipd' => 'nis', 'rombel' => 'kelas', 'rombongan_belajar' => 'kelas',
        'kompetensi_keahlian' => 'jurusan', 'program_keahlian' => 'jurusan', 'konsentrasi_keahlian' => 'jurusan', 'jk' => 'jenis_kelamin',
        'hp' => 'no_wa', 'nomor_hp' => 'no_wa', 'no_hp' => 'no_wa', 'telepon' => 'no_wa', 'nama_wali' => 'nama_wali', 'nama_ayah' => 'nama_wali',
        'nama_ptk' => 'nama', 'nik' => 'nip_nik', 'nip' => 'nip_nik', 'bidang_studi' => 'spesialisasi', 'tgl_lahir' => 'tanggal_lahir',
    ];

    private const TEMPLATES = [
        'students' => [['nisn', 'nis', 'nama', 'email', 'no_wa', 'kelas', 'jurusan', 'jenis_kelamin', 'no_wa_ortu', 'tanggal_lahir', 'nama_wali'],
            ['0081122334', '2425101', 'Rania Putri Maharani', 'rania.putri@siswa.sch.id', '6281298765432', 'XII RPL 1', 'Rekayasa Perangkat Lunak', 'P', '6281122334455', '2008-04-12', 'Hendra Maharani']],
        'teachers' => [['nama', 'email', 'no_wa', 'nip_nik', 'spesialisasi', 'maks_bimbingan'],
            ['Dewi Kartika, S.Kom.', 'dewi.kartika@sekolah.sch.id', '6281234500011', '198705122010012001', 'Rekayasa Perangkat Lunak', '20']],
        'placements' => [['nisn', 'email_guru', 'tempat_pkl', 'periode', 'divisi', 'pembimbing_industri', 'no_pembimbing_industri', 'jam_masuk', 'jam_pulang'],
            ['0081122334', 'dewi.kartika@sekolah.sch.id', 'PT Contoh Digital', 'PKL Semester Ganjil', 'Tim Web', 'Budi Santoso', '6281200000000', '08:00', '16:00']],
    ];

    public function index(): View
    {
        return view('spec.admin.import', [
            'preview' => session('import_preview'),
            'logs' => ImportLog::query()->with('user')->latest()->limit(20)->get(),
        ]);
    }

    public function template(string $kind): BinaryFileResponse
    {
        abort_unless(isset(self::TEMPLATES[$kind]), 404);
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        SpreadsheetHelper::writeXlsx($path, self::TEMPLATES[$kind]);

        return response()->download($path, "template-impor-{$kind}-jurnalin.xlsx")->deleteFileAfterSend();
    }

    public function preview(Request $request): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', 'in:students,teachers,placements'], 'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:4096']]);
        $file = $request->file('file');
        $rows = array_map(fn ($r) => $this->normalize($r), SpreadsheetHelper::readAssociativeRows($file->getRealPath(), $file->getClientOriginalExtension()));
        if (count($rows) > 2000) {
            return back()->withErrors(['file' => 'Maksimal 2.000 baris per impor. Pecah berkas menjadi beberapa bagian.']);
        }

        $seen = [];
        $checked = [];
        foreach ($rows as $i => $row) {
            [$status, $message] = $this->check($data['kind'], $row, $seen);
            $checked[] = ['line' => $i + 2, 'row' => $row, 'status' => $status, 'message' => $message];
        }

        session(['import_preview' => ['kind' => $data['kind'], 'file' => $file->getClientOriginalName(), 'rows' => $checked]]);

        return redirect()->route('admin.import')->with('status', 'Pratinjau siap. Periksa baris bermasalah sebelum memproses.');
    }

    public function commit(Request $request, ImportStudentsAction $students, ImportTeachersAction $teachers, ActivatePlacementAction $activate): RedirectResponse
    {
        $preview = session('import_preview');
        if (! $preview) {
            return redirect()->route('admin.import')->withErrors(['file' => 'Pratinjau sudah kedaluwarsa. Unggah ulang berkas.']);
        }
        $school = $request->user()->school;
        $ok = 0;
        $errors = [];
        foreach ($preview['rows'] as $item) {
            if ($item['status'] !== 'ok') {
                $errors[] = "Baris {$item['line']}: {$item['message']}";

                continue;
            }
            $error = match ($preview['kind']) {
                'students' => $this->importStudent($students, $school, $item['row']),
                'teachers' => $teachers->createOne($school, $item['row']),
                'placements' => $this->importPlacement($activate, $school, $item['row']),
            };
            $error === null ? $ok++ : $errors[] = "Baris {$item['line']}: {$error}";
        }

        $log = ImportLog::query()->create([
            'school_id' => $school->id, 'user_id' => $request->user()->id, 'kind' => $preview['kind'], 'file_name' => mb_substr((string) $preview['file'], 0, 200),
            'total' => count($preview['rows']), 'success' => $ok, 'failed' => count($errors), 'errors' => array_slice($errors, 0, 200),
        ]);
        AuditLog::record('import.processed', $log, ['kind' => $preview['kind'], 'success' => $ok, 'failed' => count($errors)]);
        session()->forget('import_preview');

        return redirect()->route('admin.import')->with('status', "Impor selesai: {$ok} berhasil, ".count($errors).' dilewati. Undangan akun terkirim ke email yang valid.')->with('import_errors', array_slice($errors, 0, 50));
    }

    /** @return array{0:string,1:string} */
    private function check(string $kind, array $row, array &$seen): array
    {
        $required = ['students' => ['nisn', 'nama', 'email', 'kelas', 'jurusan'], 'teachers' => ['nama', 'email'], 'placements' => ['nisn', 'email_guru', 'tempat_pkl']][$kind];
        $missing = array_filter($required, fn ($k) => trim((string) ($row[$k] ?? '')) === '');
        if ($missing) {
            return ['error', 'Kolom wajib kosong: '.implode(', ', $missing)];
        }
        $key = $kind === 'teachers' ? strtolower((string) $row['email']) : (string) $row['nisn'].($kind === 'students' ? '|'.strtolower((string) $row['email']) : '');
        if (isset($seen[$key])) {
            return ['duplicate', 'Duplikat dengan baris '.$seen[$key].' di berkas yang sama'];
        }
        $seen[$key] = count($seen) + 2;

        if ($kind === 'students') {
            if (Student::query()->where('nisn', $row['nisn'])->exists()) {
                return ['exists', 'NISN sudah ada, data lama tidak ditimpa'];
            }
            if (TenantContext::userEmailExists(strtolower((string) $row['email']))) {
                return ['exists', 'Email sudah dipakai akun lain'];
            }
        } elseif ($kind === 'teachers') {
            if (TenantContext::userEmailExists(strtolower((string) $row['email']))) {
                return ['exists', 'Email sudah terdaftar, data lama tidak ditimpa'];
            }
        } else {
            if (! Student::query()->where('nisn', $row['nisn'])->exists()) {
                return ['error', 'NISN belum terdaftar sebagai siswa'];
            }
            if (! User::query()->where('email', strtolower((string) $row['email_guru']))->whereHas('teacher')->exists()) {
                return ['error', 'Email guru tidak ditemukan'];
            }
            if (! Company::query()->whereRaw('lower(name) = ?', [mb_strtolower((string) $row['tempat_pkl'])])->exists()) {
                return ['error', 'Tempat PKL belum terdaftar (tambahkan di menu Tempat PKL)'];
            }
        }

        return ['ok', 'Siap diimpor'];
    }

    private function importStudent(ImportStudentsAction $action, School $school, array $row): ?string
    {
        $error = $action->createOne($school, $row);
        if ($error === null && (! empty($row['tanggal_lahir']) || ! empty($row['nama_wali']))) {
            $birth = strtotime((string) ($row['tanggal_lahir'] ?? ''));
            Student::query()->where('nisn', $row['nisn'])->update([
                'birth_date' => $birth ? date('Y-m-d', $birth) : null,
                'guardian_name' => ($row['nama_wali'] ?? null) ?: null,
            ]);
        }

        return $error;
    }

    private function importPlacement(ActivatePlacementAction $activate, School $school, array $row): ?string
    {
        $period = ! empty($row['periode'])
            ? PklPeriod::query()->whereRaw('lower(name) = ?', [mb_strtolower((string) $row['periode'])])->first()
            : PklPeriod::query()->where('is_active', true)->latest('start_date')->first();
        if ($period === null) {
            return 'Periode tidak ditemukan';
        }
        $student = Student::query()->where('nisn', $row['nisn'])->first();
        $teacher = Teacher::query()->whereHas('user', fn ($q) => $q->where('email', strtolower((string) $row['email_guru'])))->first();
        $company = Company::query()->whereRaw('lower(name) = ?', [mb_strtolower((string) $row['tempat_pkl'])])->first();
        if (! $student || ! $teacher || ! $company) {
            return 'Siswa, guru, atau tempat PKL tidak ditemukan';
        }
        $time = fn ($v, $d) => preg_match('/^\d{1,2}[:.]\d{2}$/', trim((string) $v)) ? str_pad(str_replace('.', ':', trim((string) $v)), 5, '0', STR_PAD_LEFT).':00' : $d;

        try {
            $activate->handle($school, new Placement([
                'pkl_period_id' => $period->id, 'student_id' => $student->id, 'company_id' => $company->id, 'teacher_id' => $teacher->id,
                'department_divisi' => ($row['divisi'] ?? null) ?: null, 'industry_mentor_name' => ($row['pembimbing_industri'] ?? null) ?: null,
                'industry_mentor_phone' => ($row['no_pembimbing_industri'] ?? null) ?: null,
                'work_start_time' => $time($row['jam_masuk'] ?? '', '08:00:00'), 'work_end_time' => $time($row['jam_pulang'] ?? '', '16:00:00'),
            ]));
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->first();
        }

        return null;
    }

    private function normalize(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            $k = trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower((string) $key)) ?? '', '_');
            $out[self::ALIASES[$k] ?? $k] = is_string($value) ? trim($value) : $value;
        }
        if (isset($out['jenis_kelamin'])) {
            $out['jenis_kelamin'] = strtoupper(substr((string) $out['jenis_kelamin'], 0, 1));
        }
        if (isset($out['spesialisasi'])) {
            $out['specialization'] = $out['spesialisasi'];
        }

        return $out;
    }
}
