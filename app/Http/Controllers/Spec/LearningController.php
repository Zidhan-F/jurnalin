<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\JournalStatus;
use App\Enums\PlacementStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Journal;
use App\Models\LearningObjective;
use App\Models\LearningTarget;
use App\Models\MentoringNote;
use App\Models\Placement;
use App\Models\WeeklyReflection;
use App\Models\WorkProof;
use App\Support\LearningObjectives;
use App\Support\MediaStorage;
use App\Support\Notify;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Peta Kompetensi & TP (P20), Target Belajar (P21), Bimbingan Terstruktur (P22), Refleksi Mingguan (P23),
 * Celah Kompetensi (P24), Bukti Kerja & proyek (P25/P29), Kilas Balik (P31).
 */
final class LearningController extends Controller
{
    // ---------- Siswa ----------
    public function studentHub(Request $request): View
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);
        $placement = $this->studentPlacement($student->id);
        $school = $request->user()->school;

        return view('spec.student.learning', [
            'placement' => $placement,
            'objectives' => $placement ? LearningObjectives::progress($school, $placement->id) : collect(),
            'targets' => $placement ? LearningTarget::query()->where('placement_id', $placement->id)->latest()->get() : collect(),
            'notes' => $placement ? MentoringNote::query()->where('placement_id', $placement->id)->where('visibility', 'shared')->latest()->get() : collect(),
            'reflections' => $placement ? WeeklyReflection::query()->where('placement_id', $placement->id)->latest('week_start')->get() : collect(),
            'proofs' => $placement ? WorkProof::query()->where('placement_id', $placement->id)->latest()->get() : collect(),
            'journals' => $placement ? Journal::query()->where('placement_id', $placement->id)->whereIn('status', [JournalStatus::Submitted, JournalStatus::Approved])->latest('journal_date')->limit(40)->get() : collect(),
            'weekStart' => CarbonImmutable::now($school->timezone)->startOfWeek()->toDateString(),
            'suggestions' => collect(config('pkl.journal_templates'))->flatMap(fn ($p) => $p['items'])->groupBy('objective'),
        ]);
    }

    public function storeReflection(Request $request): RedirectResponse
    {
        $placement = $this->studentPlacementOrFail($request);
        $data = $request->validate([
            'week_start' => ['required', 'date'],
            'achievements' => ['required', 'string', 'min:10', 'max:2000'],
            'difficulties' => ['nullable', 'string', 'max:2000'],
            'lessons' => ['nullable', 'string', 'max:2000'],
            'support_needed' => ['nullable', 'string', 'max:1000'],
            'next_target' => ['nullable', 'string', 'max:1000'],
        ]);
        $week = CarbonImmutable::parse($data['week_start'])->startOfWeek()->toDateString();
        WeeklyReflection::query()->updateOrCreate(['school_id' => $placement->school_id, 'placement_id' => $placement->id, 'week_start' => $week], collect($data)->except('week_start')->all());
        Notify::user($placement->teacher->user, 'reflection.submitted', 'Refleksi mingguan dari '.$request->user()->name, null, route('monitor.participant', $placement, false));

        return back()->with('status', 'Refleksi minggu ini tersimpan. Isinya tidak otomatis dipublikasikan di portofolio.');
    }

    public function storeProof(Request $request): RedirectResponse
    {
        $placement = $this->studentPlacementOrFail($request);
        $data = $request->validate([
            'kind' => ['required', 'in:proof,project'],
            'title' => ['required', 'string', 'max:200'],
            'result' => ['required', 'string', 'min:10', 'max:3000'],
            'contribution' => ['nullable', 'string', 'max:2000'],
            'team_members' => ['nullable', 'string', 'max:500'],
            'milestones' => ['nullable', 'string', 'max:2000'],
            'journal_ids' => ['required', 'array', 'min:1'],
            'journal_ids.*' => ['uuid'],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        $journalIds = Journal::query()->where('placement_id', $placement->id)->whereIn('id', $data['journal_ids'])->pluck('id')->all();
        abort_if($journalIds === [], 422, 'Pilih jurnal sumber milik Anda.');

        $proof = WorkProof::query()->create([
            ...collect($data)->except(['photo', 'journal_ids'])->all(),
            'school_id' => $placement->school_id,
            'placement_id' => $placement->id,
            'journal_ids' => $journalIds,
            'photo_path' => MediaStorage::storeStudentUpload($placement->school_id, 'workproofs', $request->file('photo')),
            'publish_allowed' => $request->boolean('publish_allowed'),
        ]);
        AuditLog::record('workproof.submitted', $proof, ['title' => $proof->title]);
        Notify::user($placement->teacher->user, 'workproof.submitted', 'Bukti Kerja baru dari '.$request->user()->name, $proof->title, route('monitor.participant', $placement, false));

        return back()->with('status', 'Bukti Kerja terkirim untuk ditinjau pembimbing.');
    }

    public function submitTarget(Request $request, LearningTarget $target): RedirectResponse
    {
        $placement = $this->studentPlacementOrFail($request);
        abort_unless($target->placement_id === $placement->id, 404);
        $data = $request->validate(['student_note' => ['required', 'string', 'min:5', 'max:1000'], 'evidence_journal_ids' => ['nullable', 'array'], 'evidence_journal_ids.*' => ['uuid']]);
        $target->update([
            'student_note' => $data['student_note'],
            'evidence_journal_ids' => Journal::query()->where('placement_id', $placement->id)->whereIn('id', $data['evidence_journal_ids'] ?? [])->pluck('id')->all(),
            'status' => 'submitted',
        ]);
        Notify::user($placement->teacher->user, 'target.submitted', 'Bukti target "'.$target->title.'" dikirim', null, route('monitor.participant', $placement, false));

        return back()->with('status', 'Bukti target terkirim ke pembimbing.');
    }

    public function respondMentoring(Request $request, MentoringNote $note): RedirectResponse
    {
        $placement = $this->studentPlacementOrFail($request);
        abort_unless($note->placement_id === $placement->id && $note->visibility === 'shared', 404);
        $note->update([...$request->validate(['student_response' => ['required', 'string', 'min:3', 'max:1000']]), 'status' => 'responded', 'responded_at' => now()]);

        return back()->with('status', 'Tanggapan bimbingan terkirim.');
    }

    public function claimObjectives(Request $request, Journal $journal): RedirectResponse
    {
        abort_unless($journal->student_id === $request->user()->student?->id, 404);
        LearningObjectives::claim($journal, $request->validate(['objective_ids' => ['nullable', 'array'], 'objective_ids.*' => ['uuid']])['objective_ids'] ?? []);

        return back()->with('status', 'Penautan TP tersimpan sebagai klaim. Guru akan memverifikasi.');
    }

    public function journey(Request $request): View
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);
        $placement = Placement::query()->with(['company', 'pklPeriod', 'assessment'])->where('student_id', $student->id)->latest()->first();
        $events = collect();
        if ($placement) {
            $events->push(['date' => $placement->pklPeriod->start_date, 'title' => 'Mulai PKL di '.$placement->company->name, 'kind' => 'milestone']);
            $journals = Journal::query()->where('placement_id', $placement->id)->where('status', JournalStatus::Approved)->orderBy('journal_date')->get();
            foreach ([1, 10, 25, 50, 100] as $n) {
                if ($journals->count() >= $n) {
                    $events->push(['date' => $journals[$n - 1]->journal_date, 'title' => "Jurnal disetujui ke-{$n}: ".$journals[$n - 1]->activity_title, 'kind' => 'journal']);
                }
            }
            foreach (WorkProof::query()->where('placement_id', $placement->id)->where('status', 'verified')->get() as $w) {
                $events->push(['date' => $w->reviewed_at ?? $w->created_at, 'title' => 'Bukti Kerja ditinjau: '.$w->title, 'kind' => 'proof']);
            }
            foreach (LearningTarget::query()->where('placement_id', $placement->id)->where('status', 'done')->get() as $t) {
                $events->push(['date' => $t->completed_at ?? $t->updated_at, 'title' => 'Target tercapai: '.$t->title, 'kind' => 'target']);
            }
            foreach (MentoringNote::query()->where('placement_id', $placement->id)->where('visibility', 'shared')->latest()->limit(3)->get() as $n) {
                $events->push(['date' => $n->created_at, 'title' => 'Pesan pembimbing: '.$n->topic, 'kind' => 'note']);
            }
            if ($placement->assessment?->finalized_at) {
                $events->push(['date' => $placement->assessment->finalized_at, 'title' => 'Nilai akhir PKL difinalkan', 'kind' => 'milestone']);
            }
        }

        return view('spec.student.journey', [
            'placement' => $placement,
            'events' => $events->sortBy(fn ($e) => $e['date'])->values(),
            'stats' => $placement ? [
                'Hari hadir' => $placement->attendances()->whereIn('status', ['present', 'late'])->count(),
                'Jurnal disetujui' => $placement->journals()->where('status', 'approved')->count(),
                'Bukti Kerja' => WorkProof::query()->where('placement_id', $placement->id)->count(),
            ] : [],
        ]);
    }

    // ---------- Guru & admin ----------
    public function storeTarget(Request $request, Placement $placement): RedirectResponse
    {
        $this->authorizePlacement($request, $placement);
        $target = LearningTarget::query()->create([
            ...$request->validate(['title' => ['required', 'string', 'max:200'], 'indicator' => ['nullable', 'string', 'max:300'], 'due_date' => ['nullable', 'date'], 'description' => ['nullable', 'string', 'max:2000']]),
            'school_id' => $placement->school_id,
            'placement_id' => $placement->id,
            'created_by_user_id' => $request->user()->id,
        ]);
        Notify::user($placement->student->user, 'target.created', 'Target belajar baru: '.$target->title, null, '/student/belajar');

        return back()->with('status', 'Target belajar ditambahkan.');
    }

    public function closeTarget(Request $request, LearningTarget $target): RedirectResponse
    {
        $this->authorizePlacement($request, $target->placement);
        $target->update(['status' => 'done', 'completed_at' => now(), 'reviewer_note' => $request->input('reviewer_note')]);

        return back()->with('status', 'Target ditandai selesai.');
    }

    public function storeMentoring(Request $request, Placement $placement): RedirectResponse
    {
        $this->authorizePlacement($request, $placement);
        $note = MentoringNote::query()->create([
            ...$request->validate(['topic' => ['required', 'string', 'max:200'], 'note' => ['required', 'string', 'max:3000'], 'action_item' => ['nullable', 'string', 'max:300'], 'due_date' => ['nullable', 'date'], 'visibility' => ['required', 'in:shared,teacher_only']]),
            'school_id' => $placement->school_id,
            'placement_id' => $placement->id,
            'author_user_id' => $request->user()->id,
        ]);
        if ($note->visibility === 'shared') {
            Notify::user($placement->student->user, 'mentoring.created', 'Catatan bimbingan: '.$note->topic, null, '/student/belajar');
        }

        return back()->with('status', 'Catatan bimbingan tersimpan.');
    }

    public function closeMentoring(Request $request, MentoringNote $note): RedirectResponse
    {
        $this->authorizePlacement($request, $note->placement);
        $note->update(['status' => 'closed', 'closed_at' => now()]);

        return back()->with('status', 'Bimbingan ditandai selesai.');
    }

    public function commentReflection(Request $request, WeeklyReflection $reflection): RedirectResponse
    {
        $this->authorizePlacement($request, $reflection->placement);
        $reflection->update([...$request->validate(['teacher_comment' => ['required', 'string', 'max:1000']]), 'commented_at' => now()]);
        Notify::user($reflection->placement->student->user, 'reflection.commented', 'Guru mengomentari refleksi mingguan Anda', null, '/student/belajar');

        return back()->with('status', 'Komentar refleksi terkirim.');
    }

    public function reviewProof(Request $request, WorkProof $proof): RedirectResponse
    {
        $this->authorizePlacement($request, $proof->placement);
        $data = $request->validate(['status' => ['required', 'in:verified,revision'], 'review_note' => ['nullable', 'string', 'max:1000']]);
        $proof->update([...$data, 'reviewer_user_id' => $request->user()->id, 'reviewer_label' => $request->user()->name, 'reviewed_at' => now()]);
        AuditLog::record('workproof.reviewed', $proof, ['status' => $data['status']]);
        Notify::user($proof->placement->student->user, 'workproof.reviewed', 'Bukti Kerja "'.$proof->title.'" '.($data['status'] === 'verified' ? 'sudah ditinjau' : 'perlu revisi'), $data['review_note'] ?? null, '/student/belajar');

        return back()->with('status', 'Tinjauan Bukti Kerja tersimpan.');
    }

    public function verifyObjectives(Request $request, Journal $journal): RedirectResponse
    {
        $this->authorizePlacement($request, $journal->placement);
        DB::table('journal_objectives')->where('journal_id', $journal->id)->where('status', 'claimed')
            ->update(['status' => 'verified', 'verified_by_user_id' => $request->user()->id, 'verified_at' => now()]);
        AuditLog::record('objectives.verified', $journal, ['date' => $journal->journal_date->toDateString()]);

        return back()->with('status', 'Klaim TP pada jurnal '.$journal->journal_date->format('d/m').' diverifikasi.');
    }

    public function objectives(Request $request): View
    {
        $school = $request->user()->school;
        LearningObjectives::forSchool($school);
        LearningObjectives::forSchool($school, 'teacher');

        return view('spec.admin.objectives', ['objectives' => LearningObjective::query()->orderBy('scope')->orderBy('sort')->orderBy('code')->get()]);
    }

    public function storeObjective(Request $request): RedirectResponse
    {
        $data = $request->validate(['scope' => ['required', 'in:pkl,teacher'], 'code' => ['required', 'string', 'max:24'], 'label' => ['required', 'string', 'max:300'], 'major' => ['nullable', 'string', 'max:100']]);
        if (LearningObjective::query()->where('scope', $data['scope'])->where('code', $data['code'])->exists()) {
            return back()->withInput()->withErrors(['code' => 'Kode TP sudah dipakai.']);
        }
        LearningObjective::query()->create([...$data, 'school_id' => $request->user()->school_id, 'sort' => 99]);

        return back()->with('status', 'TP baru ditambahkan.');
    }

    public function updateObjective(Request $request, LearningObjective $objective): RedirectResponse
    {
        $objective->update([...$request->validate(['label' => ['required', 'string', 'max:300']]), 'is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'TP diperbarui. Klaim jurnal lama tetap merujuk TP yang sama.');
    }

    private function studentPlacement(string $studentId): ?Placement
    {
        return Placement::query()->with(['teacher.user', 'pklPeriod', 'company'])->where('student_id', $studentId)->where('status', PlacementStatus::Active)->first();
    }

    private function studentPlacementOrFail(Request $request): Placement
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);

        return $this->studentPlacement($student->id) ?? abort(404, 'Penempatan aktif tidak ditemukan.');
    }

    private function authorizePlacement(Request $request, Placement $placement): void
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::SchoolAdmin || $placement->teacher_id === $user->teacher?->id, 403);
    }
}
