<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Journal;
use App\Models\MentorSignoff;
use App\Models\Placement;
use App\Support\MediaStorage;
use App\Support\MentorSignoffPeriods;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman publik untuk pembimbing di tempat PKL (tanpa akun). Akses hanya lewat token rahasia per penempatan.
 * Tidak ada sesi login, jadi konteks sekolah dibuka dari token lalu dipersempit ke sekolah penempatan itu.
 */
final class MentorSignoffController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        return $this->withPlacement($token, function (Placement $placement) use ($request): Response {
            $periods = MentorSignoffPeriods::periods($placement);
            $today = CarbonImmutable::now($placement->school->timezone)->startOfDay();

            $selected = $periods->first(fn ($p) => $p['start']->toDateString() === $request->query('mulai'))
                ?? $periods->first(fn ($p) => $p['signoff'] === null && $p['end'] <= $today)
                ?? $periods->first();

            $summary = $selected ? MentorSignoffPeriods::attendanceSummary($placement, $selected['start'], $selected['end']) : [];
            $signatureDataUri = null;
            $changed = false;
            if ($selected && $selected['signoff']) {
                $path = MediaStorage::absolutePath($selected['signoff']->signature_path);
                $signatureDataUri = $path ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
                $changed = MentorSignoffPeriods::isChangedSinceSigning($selected['signoff'], $placement);
            }

            // Render di dalam konteks sekolah: relasi yang dimuat lambat juga harus lolos RLS.
            return response(view('mentor.signoff', [
                'placement' => $placement,
                'periods' => $periods,
                'selected' => $selected,
                'summary' => $summary,
                'today' => $today,
                'signatureDataUri' => $signatureDataUri,
                'changed' => $changed,
                'token' => $request->route('token'),
            ])->render());
        });
    }

    public function sign(Request $request, string $token): Response
    {
        $validated = $request->validate([
            'period_start' => ['required', 'date_format:Y-m-d'],
            'signer_name' => ['required', 'string', 'max:120'],
            'signer_title' => ['nullable', 'string', 'max:120'],
            'statement' => ['accepted'],
            'no_drawing' => ['nullable', 'boolean'],
            'signature_data' => ['required_unless:no_drawing,1', 'nullable', 'string', 'max:2000000'],
        ], [
            'statement.accepted' => 'Centang pernyataan bahwa kegiatan di atas benar dilakukan.',
            'signature_data.required_unless' => 'Gambar tanda tangan di kotak, atau centang opsi tanpa gambar.',
        ]);

        return $this->withPlacement($token, function (Placement $placement) use ($request, $validated, $token): Response {
            $period = MentorSignoffPeriods::periods($placement)->first(fn ($p) => $p['start']->toDateString() === $validated['period_start']);
            $today = CarbonImmutable::now($placement->school->timezone)->startOfDay();
            $back = redirect()->route('mentor.show', ['token' => $token, 'mulai' => $validated['period_start']]);

            if ($period === null || $period['signoff'] !== null) {
                return $back->withErrors(['signer_name' => 'Rentang ini tidak ada atau sudah ditandatangani.']);
            }
            if ($period['end'] > $today) {
                return $back->withErrors(['signer_name' => 'Rentang ini baru bisa ditandatangani mulai '.$period['end']->translatedFormat('j F Y').'.']);
            }

            $signaturePath = null;
            if (! $request->boolean('no_drawing')) {
                $signaturePath = MediaStorage::storeStudentUpload($placement->school_id, 'signatures', null, $validated['signature_data']);
                if ($signaturePath === null) {
                    return $back->withErrors(['signature_data' => 'Gambar tanda tangan tidak terbaca. Coba gambar ulang.']);
                }
            }

            $summary = MentorSignoffPeriods::attendanceSummary($placement, $period['start'], $period['end']);

            try {
                // Savepoint: bila dua kiriman bersamaan bentrok, transaksi luar tetap bisa dipakai.
                DB::transaction(fn () => MentorSignoff::query()->create([
                    'school_id' => $placement->school_id,
                    'placement_id' => $placement->id,
                    'period_start' => $period['start']->toDateString(),
                    'period_end' => $period['end']->toDateString(),
                    'signer_name' => $validated['signer_name'],
                    'signer_title' => $validated['signer_title'] ?? null,
                    'signature_path' => $signaturePath,
                    'content_hash' => MentorSignoffPeriods::contentHash($period['journals'], $summary),
                    'journal_ids' => $period['journals']->pluck('id')->all(),
                    'attendance_summary' => $summary,
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 255),
                    'signed_at' => now(),
                ]));
            } catch (UniqueConstraintViolationException) {
                MediaStorage::discard($signaturePath);

                return $back->withErrors(['signer_name' => 'Rentang ini sudah ditandatangani.']);
            }

            AuditLog::record('mentor.signed', $placement, [
                'student' => $placement->student->user->name,
                'period' => $period['start']->toDateString().' s.d. '.$period['end']->toDateString(),
            ], $placement->school_id, $validated['signer_name']);

            return $back->with('status', 'Terima kasih. Tanda tangan tersimpan dan sudah terlihat oleh guru pembimbing.');
        });
    }

    public function note(Request $request, string $token, string $journal): Response
    {
        $validated = $request->validate(['mentor_note' => ['nullable', 'string', 'max:500']]);

        return $this->withPlacement($token, function (Placement $placement) use ($validated, $token, $journal): RedirectResponse {
            $entry = Journal::query()->where('placement_id', $placement->id)->findOrFail($journal);
            $note = trim((string) ($validated['mentor_note'] ?? ''));
            $entry->forceFill(['mentor_note' => $note !== '' ? $note : null, 'mentor_note_at' => $note !== '' ? now() : null])->save();

            AuditLog::record('mentor.noted', $entry, ['date' => $entry->journal_date->toDateString()], $placement->school_id, $placement->industry_mentor_name ?: 'Pembimbing DUDI');

            $start = MentorSignoffPeriods::bounds(CarbonImmutable::parse($entry->journal_date), $placement->school->mentor_signoff_frequency)[0];

            return redirect()->route('mentor.show', ['token' => $token, 'mulai' => $start->toDateString()])
                ->with('status', 'Catatan untuk jurnal '.$entry->journal_date->format('d/m/Y').' tersimpan.');
        });
    }

    private function withPlacement(string $token, Closure $callback): Response
    {
        // Satu-satunya pencarian lintas sekolah: berdasarkan hash token unik, lalu langsung dipersempit.
        $placement = TenantContext::runAsSuperAdmin(fn () => Placement::query()->where('mentor_link_hash', hash('sha256', $token))->first());

        if ($placement === null || $placement->mentor_link_expires_at === null || $placement->mentor_link_expires_at->isPast()) {
            return response()->view('mentor.invalid', [], 404);
        }

        return TenantContext::runInSchoolScope($placement->school_id, function () use ($placement, $callback, $token) {
            \App\Models\School::sharedLock()->findOrFail($placement->school_id);
            $placement = Placement::where('mentor_link_hash', hash('sha256', $token))->find($placement->id);
            if (! $placement || ! $placement->mentor_link_expires_at?->isFuture()) {
                return response()->view('mentor.invalid', [], 404);
            }
            $placement->load(['school', 'student.user', 'company', 'teacher.user', 'pklPeriod']);
            $row = \App\Support\GroupAccess::roster($placement->school)->where('p.id', $placement->id)->first();
            if (! $row || \App\Support\GroupAccess::phase($row, $placement->school, now($placement->school->timezone)->toDateString()) !== 'full') {
                return response()->view('mentor.invalid', [], 404);
            }

            return $callback($placement);
        });
    }
}
