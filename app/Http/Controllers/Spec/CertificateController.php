<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\JournalStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Placement;
use App\Support\Notify;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sertifikat PKL dengan QR (P41) dan verifikasi publik minimal (juga untuk surat hasil UKK, U07).
 * Bukan sertifikat kompetensi BNSP/LSP. Sertifikat dapat dicabut dengan alasan.
 */
final class CertificateController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('spec.admin.certificates', [
            'enabled' => $school->hasFeature('certificates'),
            'placements' => Placement::query()->with(['student.user', 'company', 'pklPeriod', 'assessment'])
                ->whereHas('assessment', fn ($q) => $q->whereNotNull('finalized_at'))->latest()->get(),
            'certificates' => Certificate::query()->with('student.user')->latest('issued_at')->get(),
        ]);
    }

    public function issue(Request $request, Placement $placement): RedirectResponse
    {
        $school = $request->user()->school;
        if (! $school->hasFeature('certificates')) {
            return back()->withErrors(['certificate' => 'Sertifikat QR termasuk paket Sekolah Pro. Aktifkan lewat menu Modul.']);
        }
        $assessment = $placement->assessment;
        $blocking = [];
        if ($assessment?->finalized_at === null) {
            $blocking[] = 'nilai akhir belum final';
        }
        if ($placement->journals()->whereIn('status', [JournalStatus::RevisionNeeded->value, JournalStatus::Submitted->value])->exists()) {
            $blocking[] = 'masih ada jurnal menunggu review atau revisi';
        }
        if ($blocking) {
            return back()->withErrors(['certificate' => 'Sertifikat belum dapat diterbitkan: '.implode(', ', $blocking).'.']);
        }
        if ($placement->certificates()->where('status', 'valid')->exists()) {
            return back()->withErrors(['certificate' => 'Sertifikat yang berlaku sudah ada. Cabut dulu bila perlu menerbitkan ulang.']);
        }

        $seq = Certificate::query()->where('kind', 'pkl')->count() + 1;
        $certificate = Certificate::query()->create([
            'school_id' => $placement->school_id,
            'kind' => 'pkl',
            'number' => sprintf('%03d/PKL/%s/%s', $seq, $school->npsn, now($school->timezone)->format('Y')),
            'verification_code' => self::newCode(),
            'placement_id' => $placement->id,
            'student_id' => $placement->student_id,
            'holder_name' => $placement->student->user->name,
            'payload' => [
                'school' => $school->name,
                'company' => $placement->company->name,
                'division' => $placement->department_divisi,
                'period' => $placement->pklPeriod->name,
                'from' => $placement->pklPeriod->start_date->toDateString(),
                'until' => $placement->pklPeriod->end_date->toDateString(),
                'predicate' => $assessment->predicate,
                'major' => $placement->student->major,
                'assessment_hash' => $assessment->snapshot_hash,
            ],
            'issued_by_user_id' => $request->user()->id,
            'issued_at' => now(),
        ]);
        AuditLog::record('certificate.issued', $certificate, ['number' => $certificate->number]);
        Notify::user($placement->student->user, 'certificate.issued', 'Sertifikat PKL Anda sudah terbit', null, '/student/profile');

        return back()->with('status', "Sertifikat {$certificate->number} diterbitkan.");
    }

    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:300']])['reason'];
        $certificate->update(['status' => 'revoked', 'revoked_reason' => $reason, 'revoked_at' => now()]);
        AuditLog::record('certificate.revoked', $certificate, ['number' => $certificate->number, 'reason' => $reason]);

        return back()->with('status', "Sertifikat {$certificate->number} dicabut. Halaman verifikasi kini menampilkan status dicabut.");
    }

    public function verifyForm(Request $request): Response
    {
        if ($request->filled('kode')) {
            return redirect()->route('verify.show', strtoupper(trim((string) $request->query('kode'))));
        }

        return response()->view('spec.public.verify', ['certificate' => null, 'searched' => false]);
    }

    /** Verifikasi publik: hanya data minimum (nama, sekolah, jenis, periode, status). Tanpa nilai rinci atau kontak. */
    public function verify(string $code): Response
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        $found = strlen($code) >= 8 && strlen($code) <= 32
            ? TenantContext::runAsSuperAdmin(fn () => Certificate::query()->where('verification_code', $code)->first(['id', 'school_id']))
            : null;
        $certificate = $found ? TenantContext::runInSchoolScope($found->school_id, fn () => Certificate::query()->find($found->id)) : null;

        return response()->view('spec.public.verify', ['certificate' => $certificate, 'searched' => true], $certificate ? 200 : 404);
    }

    public static function newCode(): string
    {
        return strtoupper(Str::random(10));
    }
}
