<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\AttendanceStatus;
use App\Enums\JournalStatus;
use App\Enums\PlacementStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GuestAccessGrant;
use App\Models\Journal;
use App\Models\MentorEvaluation;
use App\Models\Placement;
use App\Models\TeacherInternship;
use App\Models\UkkParticipant;
use App\Models\UkkScore;
use App\Models\UkkSession;
use App\Notifications\GuestOtpCode;
use App\Support\LearningObjectives;
use App\Support\Notify;
use App\Support\PublicToken;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman publik Mentor Access dan penguji UKK (M04-M10, M13, G07, U05). Tanpa akun, tanpa sidebar.
 * Akses hanya ke peserta dalam scope tautan yang penempatannya masih aktif. Membaca dibedakan dari
 * menilai dan mengesahkan; pengesahan wajib OTP ke kontak tervalidasi dan membuat snapshot ber-hash.
 */
final class MentorPortalController extends Controller
{
    private const OTP_MINUTES = 10;

    private const OTP_VALID_HOURS = 2;

    public function hub(Request $request, string $token): Response
    {
        return $this->withGrant($token, function (GuestAccessGrant $grant) {
            $placements = $this->scopedPlacements($grant)->load(['student.user', 'company', 'pklPeriod']);
            $evaluations = MentorEvaluation::query()->where('partner_contact_id', $grant->partner_contact_id)->get();
            $session = $grant->purpose === 'examiner' && ! empty($grant->scope['ukk_session_id'])
                ? UkkSession::query()->with('participants.student.user', 'participants.scores')->find($grant->scope['ukk_session_id']) : null;

            return $this->render('spec.portal.hub', [
                'grant' => $grant,
                'placements' => $placements,
                'pending' => Journal::query()->whereIn('placement_id', $placements->pluck('id'))->where('status', JournalStatus::Submitted)->count(),
                'evaluations' => $evaluations,
                'internships' => TeacherInternship::query()->with(['teacher.user', 'company'])->whereIn('id', $grant->scope['internship_ids'] ?? [])->get(),
                'session' => $session,
                'otpFresh' => $this->otpFresh($grant),
            ]);
        });
    }

    public function participant(Request $request, string $token, string $placement): Response
    {
        return $this->withGrant($token, function (GuestAccessGrant $grant) use ($placement, $request) {
            $p = $this->scopedPlacements($grant)->firstWhere('id', $placement) ?? abort(404);
            $p->load(['student.user', 'company', 'pklPeriod', 'school']);
            $weekStart = $request->filled('minggu') ? CarbonImmutable::parse((string) $request->query('minggu'))->startOfWeek() : CarbonImmutable::now($p->school->timezone)->startOfWeek();
            $journals = Journal::query()->where('placement_id', $p->id)->whereIn('status', [JournalStatus::Submitted, JournalStatus::Approved, JournalStatus::RevisionNeeded])
                ->whereBetween('journal_date', [$weekStart->toDateString(), $weekStart->endOfWeek()->toDateString()])->orderBy('journal_date')->get();

            return $this->render('spec.portal.participant', [
                'grant' => $grant,
                'placement' => $p,
                'journals' => $journals,
                'weekStart' => $weekStart,
                'attendance' => $p->attendances()->get()->groupBy(fn ($a) => $a->status->label())->map->count(),
                'evaluation' => MentorEvaluation::query()->where('placement_id', $p->id)->where('partner_contact_id', $grant->partner_contact_id)->latest()->first(),
                'aspects' => $p->pklPeriod->rubric()->industryAspects,
                'canApprove' => $grant->can_review && in_array($p->school->journal_approver, ['mentor', 'either'], true),
                'otpFresh' => $this->otpFresh($grant),
            ]);
        });
    }

    public function reviewJournal(Request $request, string $token, string $journal): Response
    {
        $data = $request->validate([
            'decision' => ['required', 'in:comment,revision_needed,approved'],
            'note' => ['required_unless:decision,approved', 'nullable', 'string', 'max:500'],
            'reviewed' => ['accepted'],
        ], ['reviewed.accepted' => 'Centang bahwa Anda sudah membaca jurnal ini.', 'note.required_unless' => 'Tulis catatan untuk siswa.']);

        return $this->withGrant($token, function (GuestAccessGrant $grant) use ($journal, $data, $token) {
            abort_unless($grant->can_review, 403);
            $entry = Journal::query()->with(['placement.school', 'placement.pklPeriod', 'student.user'])->findOrFail($journal);
            $p = $this->scopedPlacements($grant)->firstWhere('id', $entry->placement_id) ?? abort(404);
            $school = $p->school;
            if (CarbonImmutable::now($school->timezone)->toDateString() > $p->pklPeriod->end_date->toDateString()) {
                return back()->withErrors(['decision' => 'Masa penugasan sudah berakhir. Persetujuan jurnal tidak dapat dilakukan lagi.']);
            }

            $label = $grant->contact->name;
            if ($data['decision'] === 'comment') {
                $entry->forceFill(['mentor_note' => $data['note'], 'mentor_note_at' => now()])->save();
            } else {
                $canApprove = in_array($school->journal_approver, ['mentor', 'either'], true);
                if (! $canApprove || ! $entry->status->canTransitionTo(JournalStatus::from($data['decision']))) {
                    return back()->withErrors(['decision' => 'Program ini tidak menetapkan pembimbing lapangan sebagai penyetuju jurnal, atau status jurnal tidak dapat diubah.']);
                }
                $entry->forceFill([
                    'status' => JournalStatus::from($data['decision']),
                    'mentor_note' => $data['note'] ?: $entry->mentor_note,
                    'mentor_note_at' => now(),
                    'teacher_feedback' => $data['decision'] === 'revision_needed' ? $data['note'] : $entry->teacher_feedback,
                    'validated_at' => now(),
                ])->save();
                $entry->recordVersion(($data['decision'] === 'approved' ? 'Disetujui' : 'Revisi diminta').' pembimbing lapangan '.$label);
                Notify::user($entry->student->user, 'journal.'.$data['decision'], 'Jurnal '.$entry->journal_date->format('d/m/Y').($data['decision'] === 'approved' ? ' disetujui pembimbing lapangan' : ' perlu revisi'), $data['note'], '/student/journals');
            }
            AuditLog::record('mentor.journal_'.$data['decision'], $entry, ['grant' => $grant->id, 'date' => $entry->journal_date->toDateString()], $entry->school_id, $label);

            return redirect()->route('portal.participant', ['token' => $token, 'placement' => $p->id, 'minggu' => $entry->journal_date->startOfWeek()->toDateString()])->with('status', 'Tinjauan jurnal tersimpan.');
        });
    }

    public function saveEvaluation(Request $request, string $token, string $placement): Response
    {
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['nullable', 'string', 'max:3'], 'notes' => ['nullable', 'string', 'max:1000']]);

        return $this->withGrant($token, function (GuestAccessGrant $grant) use ($placement, $data, $token) {
            abort_unless($grant->can_evaluate, 403);
            $p = $this->scopedPlacements($grant)->firstWhere('id', $placement) ?? abort(404);
            $aspects = $p->pklPeriod->rubric()->industryAspects;
            $scores = $this->cleanScores($aspects, $data['scores']);
            $existing = MentorEvaluation::query()->where('placement_id', $p->id)->where('partner_contact_id', $grant->partner_contact_id)->latest()->first();
            if ($existing?->status === 'final') {
                return back()->withErrors(['scores' => 'Evaluasi sudah disahkan. Perubahan dilakukan sekolah sebagai versi baru.']);
            }

            MentorEvaluation::query()->updateOrCreate(['id' => $existing?->id ?? (string) \Illuminate\Support\Str::uuid()], [
                'school_id' => $p->school_id, 'placement_id' => $p->id, 'partner_contact_id' => $grant->partner_contact_id, 'grant_id' => $grant->id,
                'rubric' => $aspects, 'scores' => $scores, 'notes' => $data['notes'] ?? null, 'status' => 'draft',
            ]);

            return redirect()->route('portal.participant', ['token' => $token, 'placement' => $p->id])->with('status', 'Draf evaluasi tersimpan. Tinjau lagi lalu sahkan dengan kode verifikasi.');
        });
    }

    public function sendOtp(Request $request, string $token): Response
    {
        $code = $request->input('otp');

        return $this->withGrant($token, function (GuestAccessGrant $grant) use ($code) {
            $contact = $grant->contact;
            if ($code !== null) {
                if ($grant->otp_attempts >= 5 || $grant->otp_expires_at === null || $grant->otp_expires_at->isPast() || ! hash_equals((string) $grant->otp_hash, hash('sha256', (string) $code))) {
                    $grant->increment('otp_attempts');
                    AuditLog::record('guest.otp_failed', $grant, [], $grant->school_id, $contact->name);

                    return back()->withErrors(['otp' => 'Kode verifikasi salah atau kedaluwarsa. Minta kode baru.']);
                }
                $grant->update(['otp_verified_at' => now(), 'otp_hash' => null, 'otp_attempts' => 0]);
                AuditLog::record('guest.otp_verified', $grant, [], $grant->school_id, $contact->name);

                return back()->with('status', 'Identitas terverifikasi. Anda dapat mengesahkan penilaian selama 2 jam.');
            }
            if (empty($contact->email)) {
                return back()->withErrors(['otp' => 'Kontak Anda belum memiliki email tervalidasi. Hubungi guru pembimbing untuk melengkapi, atau gunakan formulir kertas.']);
            }
            $otp = (string) random_int(100000, 999999);
            $grant->update(['otp_hash' => hash('sha256', $otp), 'otp_expires_at' => now()->addMinutes(self::OTP_MINUTES), 'otp_attempts' => 0]);
            Notification::route('mail', $contact->email)->notify(new GuestOtpCode($otp, $grant->school->name));
            $masked = preg_replace('/(?<=.).(?=[^@]*@)/', '*', $contact->email);

            return back()->with('status', "Kode verifikasi dikirim ke {$masked}. Berlaku ".self::OTP_MINUTES.' menit.');
        });
    }

    public function confirmEvaluation(Request $request, string $token, string $evaluation): Response
    {
        $request->validate(['statement' => ['accepted']], ['statement.accepted' => 'Centang pernyataan persetujuan sebelum mengesahkan.']);

        return $this->withGrant($token, function (GuestAccessGrant $grant) use ($evaluation, $request) {
            $ev = MentorEvaluation::query()->where('partner_contact_id', $grant->partner_contact_id)->findOrFail($evaluation);
            if ($ev->placement_id && ! $this->scopedPlacements($grant)->contains('id', $ev->placement_id)) {
                abort(404);
            }
            if ($ev->teacher_internship_id && ! in_array($ev->teacher_internship_id, $grant->scope['internship_ids'] ?? [], true)) {
                abort(404);
            }
            if (! $this->otpFresh($grant)) {
                return back()->withErrors(['otp' => 'Verifikasi identitas dengan kode OTP terlebih dahulu.']);
            }
            if ($ev->status === 'final') {
                return back()->withErrors(['statement' => 'Evaluasi ini sudah disahkan.']);
            }
            $snapshot = ['rubric' => $ev->rubric, 'scores' => $ev->scores, 'notes' => $ev->notes, 'mentor' => $grant->contact->only(['name', 'position', 'email']), 'version' => $ev->version];
            $ev->update([
                'status' => 'final', 'statement_accepted_at' => now(), 'otp_verified_at' => $grant->otp_verified_at, 'finalized_at' => now(),
                'content_hash' => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE)),
            ]);
            AuditLog::record('mentor.evaluation_final', $ev, ['hash' => $ev->content_hash, 'ip' => $request->ip()], $ev->school_id, $grant->contact->name);
            $teacherUser = $ev->placement?->teacher?->user ?? $ev->internship?->teacher?->user;
            Notify::user($teacherUser, 'mentor.evaluation_final', 'Evaluasi pembimbing lapangan sudah disahkan', null, $ev->placement ? '/teacher/assessments' : '/teacher/magang');

            return back()->with('status', 'Evaluasi disahkan dan dikunci. Terima kasih atas penilaian Anda.');
        });
    }

    public function internship(Request $request, string $token, string $internship): Response
    {
        return $this->withGrant($token, function (GuestAccessGrant $grant) use ($internship) {
            abort_unless(in_array($internship, $grant->scope['internship_ids'] ?? [], true), 404);
            $item = TeacherInternship::query()->with(['teacher.user', 'company', 'journals' => fn ($q) => $q->latest('journal_date')->limit(30)])->findOrFail($internship);

            return $this->render('spec.portal.internship', [
                'grant' => $grant,
                'internship' => $item,
                'objectives' => LearningObjectives::forSchool($item->school, 'teacher'),
                'evaluation' => MentorEvaluation::query()->where('teacher_internship_id', $item->id)->where('partner_contact_id', $grant->partner_contact_id)->latest()->first(),
                'otpFresh' => $this->otpFresh($grant),
            ]);
        });
    }

    public function evaluateInternship(Request $request, string $token, string $internship): Response
    {
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['nullable', 'string', 'max:3'], 'notes' => ['nullable', 'string', 'max:1000']]);

        return $this->withGrant($token, function (GuestAccessGrant $grant) use ($internship, $data) {
            abort_unless(in_array($internship, $grant->scope['internship_ids'] ?? [], true) && $grant->can_evaluate, 404);
            $item = TeacherInternship::query()->findOrFail($internship);
            $aspects = LearningObjectives::forSchool($item->school, 'teacher')->map(fn ($o) => ['code' => $o->code, 'label' => $o->label, 'weight' => 1])->values()->all();
            $existing = MentorEvaluation::query()->where('teacher_internship_id', $item->id)->where('partner_contact_id', $grant->partner_contact_id)->latest()->first();
            if ($existing?->status === 'final') {
                return back()->withErrors(['scores' => 'Evaluasi sudah disahkan.']);
            }
            MentorEvaluation::query()->updateOrCreate(['id' => $existing?->id ?? (string) \Illuminate\Support\Str::uuid()], [
                'school_id' => $item->school_id, 'teacher_internship_id' => $item->id, 'partner_contact_id' => $grant->partner_contact_id, 'grant_id' => $grant->id,
                'rubric' => $aspects, 'scores' => $this->cleanScores($aspects, $data['scores']), 'notes' => $data['notes'] ?? null, 'status' => 'draft',
            ]);

            return back()->with('status', 'Draf evaluasi magang guru tersimpan.');
        });
    }

    public function scoreUkk(Request $request, string $token, string $participant): Response
    {
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['required', 'numeric', 'min:0', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000']]);

        return $this->withGrant($token, function (GuestAccessGrant $grant) use ($participant, $data) {
            abort_unless($grant->purpose === 'examiner', 403);
            if (! $this->otpFresh($grant)) {
                return back()->withErrors(['otp' => 'Konfirmasi identitas penguji dengan kode OTP sebelum menilai.']);
            }
            $p = UkkParticipant::query()->with('session')->findOrFail($participant);
            abort_unless($p->ukk_session_id === ($grant->scope['ukk_session_id'] ?? null), 404);
            if ($p->session->status === 'final') {
                return back()->withErrors(['scores' => 'Sesi UKK sudah difinalkan koordinator.']);
            }
            $codes = array_column($p->session->instrument['aspects'] ?? [], 'code');
            UkkScore::query()->updateOrCreate(
                ['ukk_participant_id' => $p->id, 'examiner_contact_id' => $grant->partner_contact_id],
                ['school_id' => $p->school_id, 'instrument_version' => $p->session->instrument_version, 'scores' => array_intersect_key($data['scores'], array_flip($codes)), 'notes' => $data['notes'] ?? null, 'confirmed_at' => now()],
            );
            if ($p->session->status === 'prepared') {
                $p->session->update(['status' => 'ongoing']);
            }
            AuditLog::record('ukk.scored_external', $p, ['examiner' => $grant->contact->name], $p->school_id, $grant->contact->name);

            return back()->with('status', 'Nilai peserta '.$p->participant_number.' tersimpan atas nama Anda.');
        });
    }

    /** @param list<array{code:string}> $aspects */
    private function cleanScores(array $aspects, array $input): array
    {
        $scores = [];
        foreach ($aspects as $a) {
            $v = $input[$a['code']] ?? null;
            // "NA" = tidak dapat dinilai: kemampuan yang tidak diamati tidak dipaksa diberi skor (M08).
            $scores[$a['code']] = ($v === null || $v === '' || strtoupper((string) $v) === 'NA') ? 'NA' : max(0, min(100, (int) $v));
        }

        return $scores;
    }

    private function otpFresh(GuestAccessGrant $grant): bool
    {
        return $grant->otp_verified_at !== null && $grant->otp_verified_at->greaterThan(now()->subHours(self::OTP_VALID_HOURS));
    }

    private function scopedPlacements(GuestAccessGrant $grant)
    {
        return Placement::query()->whereIn('id', $grant->scope['placement_ids'] ?? [])->where('status', PlacementStatus::Active)->get();
    }

    private function render(string $view, array $data): Response
    {
        return response(view($view, $data)->render());
    }

    private function withGrant(string $token, Closure $callback): Response
    {
        $result = PublicToken::within(GuestAccessGrant::class, $token, function (GuestAccessGrant $grant) use ($callback, $token) {
            if ($grant->revoked_at !== null || $grant->expires_at->isPast()) {
                return null;
            }
            $grant->load(['contact', 'school']);
            $grant->forceFill(['last_used_at' => now()])->saveQuietly();
            request()->attributes->set('portal_token', $token);

            return $callback($grant);
        });

        return $result ?? response()->view('spec.public.invalid', ['message' => 'Tautan ini sudah dicabut, diganti, atau masa berlakunya habis. Minta tautan terbaru kepada guru pembimbing.'], 404);
    }
}
