<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\JournalStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Journal;
use App\Models\Passport;
use App\Models\Placement;
use App\Models\ReadinessResult;
use App\Models\Student;
use App\Models\UkkParticipant;
use App\Models\WorkProof;
use App\Support\LearningObjectives;
use App\Support\PublicToken;
use App\Support\Qr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paspor Kompetensi (P30) & integrasi UKK (U08). Privat secara bawaan; siswa memilih bagian yang tampil,
 * mengatur masa berlaku, dan bisa mencabut tautan. Label membedakan klaim pribadi, tinjauan pembimbing,
 * dan data final sekolah. Bukan portal BKK atau lowongan.
 */
final class PassportController extends Controller
{
    public const DEFAULT_SETTINGS = ['journals' => false, 'proofs' => true, 'objectives' => true, 'grade' => false, 'certificates' => true, 'ukk' => true, 'readiness' => false, 'about' => ''];

    public function edit(Request $request): View
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);
        $passport = Passport::query()->where('student_id', $student->id)->first();
        $settings = $passport?->settings ?? self::DEFAULT_SETTINGS;
        $url = $passport?->token && ! $passport->revoked_at && (! $passport->expires_at || $passport->expires_at->isFuture()) ? route('passport.public', $passport->token) : null;

        return view('spec.student.passport', [
            'enabled' => $request->user()->school->hasFeature('passport'),
            'passport' => $passport,
            'settings' => $settings,
            'url' => $url,
            'qr' => $url ? Qr::dataUri($url, 140) : null,
            'data' => self::compile($student, $settings),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);
        abort_unless($request->user()->school->hasFeature('passport'), 403, 'Paspor Kompetensi termasuk paket Sekolah Pro.');
        $data = $request->validate(['about' => ['nullable', 'string', 'max:600'], 'expires_days' => ['required', 'integer', 'min:7', 'max:365'], 'publish' => ['nullable', 'boolean']]);

        $settings = ['about' => $data['about'] ?? ''];
        foreach (['journals', 'proofs', 'objectives', 'grade', 'certificates', 'ukk', 'readiness'] as $key) {
            $settings[$key] = $request->boolean("show_{$key}");
        }

        $passport = Passport::query()->firstOrNew(['student_id' => $student->id], ['school_id' => $student->school_id]);
        $passport->settings = $settings;
        $passport->expires_at = now()->addDays((int) $data['expires_days']);
        if ($request->boolean('publish') && ($passport->token === null || $passport->revoked_at !== null)) {
            [$token, $hash] = PublicToken::make(40);
            $passport->token = $token;
            $passport->token_hash = $hash;
            $passport->revoked_at = null;
        }
        $passport->save();
        AuditLog::record('passport.updated', $passport, ['published' => $passport->token_hash !== null]);

        return back()->with('status', $request->boolean('publish') ? 'Paspor diterbitkan. Bagikan tautan atau QR hanya kepada pihak yang Anda pilih.' : 'Pengaturan paspor disimpan.');
    }

    public function revoke(Request $request): RedirectResponse
    {
        $passport = Passport::query()->where('student_id', $request->user()->student?->id)->firstOrFail();
        $passport->update(['revoked_at' => now(), 'token_hash' => null, 'token' => null]);
        AuditLog::record('passport.revoked', $passport);

        return back()->with('status', 'Tautan paspor dicabut dan langsung tidak bisa dibuka.');
    }

    public function publicView(string $token): Response
    {
        $result = PublicToken::within(Passport::class, $token, function (Passport $passport) {
            if ($passport->revoked_at || ($passport->expires_at && $passport->expires_at->isPast())) {
                return null;
            }
            $student = $passport->student()->with('user', 'school')->first();

            return response(view('spec.public.passport', ['student' => $student, 'data' => self::compile($student, $passport->settings), 'passport' => $passport])->render());
        });

        return $result ?? response()->view('spec.public.invalid', ['message' => 'Paspor ini sudah dicabut pemiliknya atau masa berlakunya habis.'], 404);
    }

    /** Hanya data yang dipilih siswa; "terverifikasi" hanya untuk data final. */
    public static function compile(Student $student, array $settings): array
    {
        $placements = Placement::query()->with(['company', 'pklPeriod', 'assessment'])->where('student_id', $student->id)->latest()->get();
        $ids = $placements->pluck('id');

        return [
            'about' => $settings['about'] ?? '',
            'placements' => $placements->map(fn ($p) => ['company' => $p->company->name, 'division' => $p->department_divisi, 'period' => $p->pklPeriod->name,
                'from' => $p->pklPeriod->start_date, 'until' => $p->pklPeriod->end_date,
                'grade' => ($settings['grade'] ?? false) && $p->assessment?->finalized_at ? ['score' => $p->assessment->final_score, 'predicate' => $p->assessment->predicate, 'description' => $p->assessment->achievement_description] : null]),
            'objectives' => ($settings['objectives'] ?? false) && $placements->first() ? LearningObjectives::progress($student->school, $placements->first()->id)->filter(fn ($r) => $r['verified'] > 0)->values() : collect(),
            'proofs' => ($settings['proofs'] ?? false) ? WorkProof::query()->whereIn('placement_id', $ids)->where('status', 'verified')->where('publish_allowed', true)->get() : collect(),
            'journals' => ($settings['journals'] ?? false) ? Journal::query()->whereIn('placement_id', $ids)->where('status', JournalStatus::Approved)->latest('journal_date')->limit(15)->get(['journal_date', 'activity_title', 'work_unit']) : collect(),
            'certificates' => ($settings['certificates'] ?? false) ? Certificate::query()->where('student_id', $student->id)->where('status', 'valid')->get() : collect(),
            'ukk' => ($settings['ukk'] ?? false) ? UkkParticipant::query()->with('session')->where('student_id', $student->id)->whereHas('session', fn ($q) => $q->where('status', 'final'))->get() : collect(),
            'readiness' => ($settings['readiness'] ?? false) ? ReadinessResult::query()->with('module')->where('student_id', $student->id)->where('passed', true)->get() : collect(),
        ];
    }
}
