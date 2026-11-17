<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Company;
use App\Models\GuestAccessGrant;
use App\Models\PartnerContact;
use App\Models\Student;
use App\Models\UkkParticipant;
use App\Models\UkkScore;
use App\Models\UkkSession;
use App\Support\Notify;
use App\Support\PublicToken;
use App\ValueObjects\AssessmentRubric;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Uji Kompetensi Keahlian (U01-U08). Instrumen dapat dikonfigurasi (juknis berubah tiap tahun) dan berversi;
 * nilai tiap penguji disimpan terpisah; koordinator memfinalkan; surat keterangan ber-QR bukan sertifikat BNSP/LSP.
 */
final class UkkController extends Controller
{
    public const DEFAULT_INSTRUMENT = ['aspects' => [
        ['code' => 'persiapan', 'label' => 'Persiapan kerja', 'weight' => 15],
        ['code' => 'proses', 'label' => 'Proses / sistematika kerja', 'weight' => 40],
        ['code' => 'hasil', 'label' => 'Hasil kerja', 'weight' => 30],
        ['code' => 'sikap', 'label' => 'Sikap kerja & K3', 'weight' => 15],
    ]];

    public function index(): View
    {
        return view('spec.ukk.index', [
            'sessions' => UkkSession::query()->withCount('participants')->latest('scheduled_at')->get(),
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'], 'academic_year' => ['required', 'string', 'max:16'], 'concentration' => ['required', 'string', 'max:120'],
            'venue' => ['required', 'string', 'max:200'], 'venue_company_id' => ['nullable', 'uuid'], 'scheduled_at' => ['required', 'date'], 'quota' => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        if (! empty($data['venue_company_id'])) {
            Company::query()->findOrFail($data['venue_company_id']);
        }
        $session = UkkSession::query()->create([...$data, 'school_id' => $request->user()->school_id, 'instrument' => self::DEFAULT_INSTRUMENT]);

        return redirect()->route('admin.ukk.show', $session)->with('status', 'Sesi UKK dibuat dengan instrumen bawaan. Sesuaikan dengan juknis tahun berjalan.');
    }

    public function show(UkkSession $session): View
    {
        $session->load(['participants.student.user', 'participants.scores.examinerContact', 'participants.scores.examinerUser', 'venueCompany']);

        return view('spec.ukk.show', [
            'session' => $session,
            'students' => Student::query()->with('user')->whereNotIn('id', $session->participants->pluck('student_id'))->orderBy('class_name')->get(),
            'examiners' => PartnerContact::query()->with('company')->where('kind', 'examiner')->where('is_active', true)->get(),
            'grants' => GuestAccessGrant::query()->with('contact')->where('purpose', 'examiner')->get()->filter(fn ($g) => ($g->scope['ukk_session_id'] ?? null) === $session->id),
            'certificates' => Certificate::query()->where('kind', 'ukk')->whereIn('ukk_participant_id', $session->participants->pluck('id'))->get()->keyBy('ukk_participant_id'),
        ]);
    }

    public function addParticipants(Request $request, UkkSession $session): RedirectResponse
    {
        $this->editable($session);
        $ids = $request->validate(['student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['uuid']])['student_ids'];
        $students = Student::query()->whereIn('id', $ids)->get(); // RLS + scope: hanya siswa milik tenant.
        $count = $session->participants()->count();
        if ($count + $students->count() > $session->quota) {
            return back()->withErrors(['student_ids' => "Kuota sesi {$session->quota} peserta akan terlampaui."]);
        }
        foreach ($students as $i => $s) {
            UkkParticipant::query()->firstOrCreate(['ukk_session_id' => $session->id, 'student_id' => $s->id], ['school_id' => $session->school_id, 'participant_number' => sprintf('%s-%03d', Str::upper(Str::substr(Str::slug($session->concentration, ''), 0, 4)), $count + $i + 1)]);
        }

        return back()->with('status', $students->count().' peserta ditambahkan dari data siswa.');
    }

    public function updateInstrument(Request $request, UkkSession $session): RedirectResponse
    {
        abort_if($session->status === 'final', 422, 'Sesi sudah final.');
        $text = $request->validate(['aspects_text' => ['required', 'string', 'max:3000']])['aspects_text'];
        $aspects = collect(preg_split('/\r?\n/', $text))->map(fn ($l) => array_map('trim', explode('|', $l)))->filter(fn ($p) => count($p) === 3)
            ->map(fn ($p) => ['code' => Str::slug($p[0], '_'), 'label' => $p[1], 'weight' => (int) $p[2]])->values()->all();
        if (array_sum(array_column($aspects, 'weight')) !== 100) {
            return back()->withErrors(['aspects_text' => 'Total bobot aspek wajib 100.']);
        }
        // Instrumen berubah setelah penilaian dimulai: versi baru, nilai lama tetap mengacu versinya.
        $started = UkkScore::query()->whereIn('ukk_participant_id', $session->participants()->pluck('id'))->exists();
        $session->update(['instrument' => ['aspects' => $aspects], 'instrument_version' => $started ? $session->instrument_version + 1 : $session->instrument_version]);
        AuditLog::record('ukk.instrument_updated', $session, ['version' => $session->instrument_version]);

        return back()->with('status', 'Instrumen disimpan'.($started ? ' sebagai versi '.$session->instrument_version.'.' : '.'));
    }

    public function inviteExaminer(Request $request, UkkSession $session): RedirectResponse
    {
        $data = $request->validate(['partner_contact_id' => ['nullable', 'uuid'], 'name' => ['required_without:partner_contact_id', 'nullable', 'string', 'max:120'],
            'email' => ['required_without:partner_contact_id', 'nullable', 'email'], 'phone' => ['nullable', 'string', 'max:24'], 'position' => ['nullable', 'string', 'max:120'], 'company_id' => ['nullable', 'uuid']]);
        $contact = ! empty($data['partner_contact_id'])
            ? PartnerContact::query()->findOrFail($data['partner_contact_id'])
            : PartnerContact::query()->create(['school_id' => $session->school_id, 'company_id' => $data['company_id'] ?? null, 'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                'position' => $data['position'] ?? null, 'kind' => 'examiner', 'valid_from' => now()->toDateString()]);
        [$token, $hash] = PublicToken::make(48);
        GuestAccessGrant::query()->create(['school_id' => $session->school_id, 'partner_contact_id' => $contact->id, 'purpose' => 'examiner', 'token_hash' => $hash, 'token' => $token,
            'scope' => ['ukk_session_id' => $session->id], 'can_evaluate' => true, 'expires_at' => $session->scheduled_at->copy()->addDays(14), 'created_by_user_id' => $request->user()->id]);
        $url = route('portal.hub', $token);
        AuditLog::record('ukk.examiner_invited', $session, ['examiner' => $contact->name]);

        return back()->with('status', 'Tautan penguji dibuat. Penguji mengonfirmasi identitas dengan kode email sebelum menilai.')
            ->with('magic_link', ['url' => $url, 'name' => $contact->name, 'wa' => Notify::waShare($contact->phone, "Yth. {$contact->name}, tautan penilaian UKK {$session->concentration} dari sekolah: {$url}")]);
    }

    public function scoreInternal(Request $request, UkkSession $session, UkkParticipant $participant): RedirectResponse
    {
        $this->editable($session);
        abort_unless($participant->ukk_session_id === $session->id, 404);
        $codes = array_column($session->instrument['aspects'], 'code');
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['required', 'numeric', 'min:0', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000']]);
        UkkScore::query()->updateOrCreate(['ukk_participant_id' => $participant->id, 'examiner_user_id' => $request->user()->id],
            ['school_id' => $session->school_id, 'instrument_version' => $session->instrument_version, 'scores' => array_intersect_key($data['scores'], array_flip($codes)), 'notes' => $data['notes'] ?? null, 'confirmed_at' => now()]);
        if ($session->status === 'prepared') {
            $session->update(['status' => 'ongoing']);
        }

        return back()->with('status', 'Nilai penguji internal tersimpan.');
    }

    public function finalize(Request $request, UkkSession $session): RedirectResponse
    {
        $this->editable($session);
        $session->load('participants.scores');
        $missing = $session->participants->filter(fn ($p) => $p->scores->isEmpty());
        if ($session->participants->isEmpty() || $missing->isNotEmpty()) {
            return back()->withErrors(['finalize' => 'Semua peserta harus memiliki nilai dari minimal satu penguji. Belum: '.$missing->count().' peserta.']);
        }
        $predicates = AssessmentRubric::defaultTemplate()['predicates'];
        $rubric = AssessmentRubric::fromArray(AssessmentRubric::defaultTemplate());
        foreach ($session->participants as $p) {
            $perExaminer = $p->scores->map(fn ($s) => collect($session->instrument['aspects'])->sum(fn ($a) => (float) ($s->scores[$a['code']] ?? 0) * $a['weight'] / 100));
            $final = round($perExaminer->avg(), 2);
            $p->update(['final_score' => $final, 'predicate' => $rubric->resolvePredicate($final), 'snapshot' => [
                'instrument' => $session->instrument, 'instrument_version' => $session->instrument_version, 'per_examiner' => $p->scores->map(fn ($s) => ['scores' => $s->scores, 'by' => $s->examiner_contact_id ?? $s->examiner_user_id])->values(),
                'final' => $final, 'predicates' => $predicates,
            ]]);
        }
        $session->update(['status' => 'final', 'finalized_at' => now(), 'finalized_by_user_id' => $request->user()->id, 'minutes' => $request->input('minutes') ?: $session->minutes]);
        AuditLog::record('ukk.finalized', $session, ['participants' => $session->participants->count()]);

        return back()->with('status', 'Rekap UKK difinalkan dan snapshot dikunci.');
    }

    public function issueCertificates(Request $request, UkkSession $session): RedirectResponse
    {
        abort_unless($session->status === 'final', 422, 'Finalkan sesi terlebih dahulu.');
        $school = $request->user()->school;
        $issued = 0;
        foreach ($session->participants()->with('student.user')->get() as $p) {
            if (Certificate::query()->where('ukk_participant_id', $p->id)->where('status', 'valid')->exists()) {
                continue;
            }
            $seq = Certificate::query()->where('kind', 'ukk')->count() + 1;
            Certificate::query()->create(['school_id' => $session->school_id, 'kind' => 'ukk', 'number' => sprintf('%03d/UKK/%s/%s', $seq, $school->npsn, $session->scheduled_at->format('Y')),
                'verification_code' => CertificateController::newCode(), 'ukk_participant_id' => $p->id, 'student_id' => $p->student_id, 'holder_name' => $p->student->user->name,
                'payload' => ['school' => $school->name, 'concentration' => $session->concentration, 'session' => $session->name, 'score' => number_format((float) $p->final_score, 2), 'predicate' => $p->predicate],
                'issued_by_user_id' => $request->user()->id, 'issued_at' => now()]);
            $issued++;
        }

        return back()->with('status', "{$issued} surat keterangan hasil UKK diterbitkan (bukan sertifikat BNSP/LSP).");
    }

    public function recapCsv(UkkSession $session): StreamedResponse
    {
        $session->load('participants.student.user');

        return response()->streamDownload(function () use ($session): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['No. peserta', 'Nama', 'NISN', 'Nilai akhir', 'Predikat']);
            foreach ($session->participants as $p) {
                fputcsv($out, [$p->participant_number, $p->student->user->name, $p->student->nisn, $p->final_score, $p->predicate]);
            }
            fclose($out);
        }, 'rekap-ukk-'.Str::slug($session->name).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function editable(UkkSession $session): void
    {
        abort_if($session->status === 'final', 422, 'Sesi UKK sudah final dan terkunci.');
    }
}
