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
 * Pencabutan dan verifikasi publik minimal surat keterangan hasil UKK (U07). Sertifikat PKL tidak
 * diterbitkan sekolah karena sertifikat PKL berasal dari industri. Bukan sertifikat BNSP/LSP.
 */
final class CertificateController extends Controller
{
    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:300']])['reason'];
        $certificate->update(['status' => 'revoked', 'revoked_reason' => $reason, 'revoked_at' => now()]);
        AuditLog::record('certificate.revoked', $certificate, ['number' => $certificate->number, 'reason' => $reason]);

        return back()->with('status', "Surat {$certificate->number} dicabut. Halaman verifikasi kini menampilkan status dicabut.");
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
