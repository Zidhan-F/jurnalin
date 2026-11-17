<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Assessment\CalculateFinalScoreAction;
use App\Actions\Journal\ValidateJournalAction;
use App\Enums\JournalStatus;
use App\Enums\PlacementStatus;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Journal;
use App\Models\Placement;
use App\Support\MediaStorage;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TeacherController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $school = $user->school;
        $teacher = $user->teacher;
        abort_if($teacher === null, 403, 'Profil guru tidak ditemukan.');

        $nowLocal = CarbonImmutable::now($school->timezone);
        $todayDate = $nowLocal->toDateString();

        $placements = Placement::query()
            ->with(['student.user', 'company', 'pklPeriod', 'assessment'])
            ->whereKey($request->attributes->get('full_placements'))
            ->where('teacher_id', $teacher->id)
            ->where('status', PlacementStatus::Active)
            ->get();

        $placementIds = $placements->pluck('id');

        $todayAttendedIds = Attendance::query()
            ->whereIn('placement_id', $placementIds)
            ->whereDate('attendance_date', $todayDate)
            ->pluck('placement_id')
            ->all();

        $missingToday = $placements
            ->reject(fn (Placement $p) => in_array($p->id, $todayAttendedIds, true))
            ->map(function (Placement $p) use ($nowLocal, $todayDate, $school) {
                $deadline = CarbonImmutable::parse("{$todayDate} {$p->work_start_time}", $school->timezone)
                    ->addMinutes($school->late_tolerance_minutes);

                return [
                    'placement' => $p,
                    'is_overdue' => $nowLocal->greaterThan($deadline),
                    'deadline_time' => $deadline->format('H:i'),
                ];
            })
            ->sortByDesc('is_overdue')
            ->values();

        $pendingJournals = Journal::query()
            ->with(['student.user', 'placement.company'])
            ->whereIn('placement_id', $placementIds->merge($request->attributes->get('grace_placements', [])))
            ->where('status', JournalStatus::Submitted)
            ->orderBy('journal_date')
            ->orderBy('created_at')
            ->get();

        $pendingExceptions = Attendance::query()
            ->with(['student.user', 'placement.company'])
            ->whereIn('placement_id', $placementIds)
            ->whereNull('approved_by_teacher_id')
            ->whereNull('leave_request_id')
            ->where(function ($q): void {
                $q->where('is_within_radius', false)
                    ->orWhereIn('status', ['sick', 'permission'])
                    ->orWhere('review_status', 'pending');
            })
            ->orderByDesc('attendance_date')
            ->get();

        $pendingLeaves = \App\Models\LeaveRequest::query()->with(['student.user', 'placement.company'])
            ->whereIn('placement_id', $placementIds)->where('status', 'submitted')->orderBy('start_date')->get();
        $presentToday = Attendance::query()->whereIn('placement_id', $placementIds)->whereDate('attendance_date', $todayDate)
            ->get()->groupBy(fn ($a) => $a->status->label())->map->count();

        return view('teacher.index', [
            'school' => $school,
            'teacher' => $teacher,
            'todayFormatted' => $nowLocal->translatedFormat('d M Y, H:i'),
            'placements' => $placements,
            'missingToday' => $missingToday,
            'pendingJournals' => $pendingJournals,
            'pendingExceptions' => $pendingExceptions,
            'pendingLeaves' => $pendingLeaves,
            'presentToday' => $presentToday,
            'alerts' => \App\Support\PklMonitor::alerts($placements, $todayDate)->take(8),
            'weekly' => \App\Support\PklMonitor::weekly($placements, $todayDate),
        ]);
    }

    public function approveJournal(Request $request, Journal $journal, ValidateJournalAction $action): RedirectResponse
    {
        $teacher = $request->user()->teacher;
        abort_if($teacher === null, 403);

        try {
            $action->handle($journal, $teacher, JournalStatus::Approved, $request->string('feedback')->toString());
            AuditLog::record('journal.approved', $journal, ['date' => $journal->journal_date->toDateString()]);
        } catch (DomainException $e) {
            return back()->withErrors(['journal' => $e->getMessage()]);
        }

        return back()->with('status', "Jurnal {$journal->student->user->name} tanggal {$journal->journal_date->format('d/m/Y')} disetujui.");
    }

    public function bulkApproveJournals(Request $request, ValidateJournalAction $action): RedirectResponse
    {
        $teacher = $request->user()->teacher;
        abort_if($teacher === null, 403);

        $ids = $request->validate([
            'journal_ids' => ['required', 'array', 'min:1'],
            'journal_ids.*' => ['uuid'],
        ])['journal_ids'];

        $journals = Journal::query()->whereIn('id', $ids)->get();
        $approvedCount = 0;

        foreach ($journals as $journal) {
            try {
                $action->handle($journal, $teacher, JournalStatus::Approved);
                AuditLog::record('journal.approved', $journal, ['date' => $journal->journal_date->toDateString(), 'bulk' => true]);
                $approvedCount++;
            } catch (DomainException) {
                continue;
            }
        }

        return back()->with('status', "{$approvedCount} jurnal berhasil disetujui sekaligus.");
    }

    public function requestRevisionJournal(Request $request, Journal $journal, ValidateJournalAction $action): RedirectResponse
    {
        $teacher = $request->user()->teacher;
        abort_if($teacher === null, 403);

        $validated = $request->validate([
            'teacher_feedback' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $action->handle($journal, $teacher, JournalStatus::RevisionNeeded, $validated['teacher_feedback']);
            AuditLog::record('journal.revision_requested', $journal, ['date' => $journal->journal_date->toDateString()]);
        } catch (DomainException $e) {
            return back()->withErrors(['teacher_feedback' => $e->getMessage()]);
        }

        return back()->with('status', "Permintaan revisi terkirim ke {$journal->student->user->name}.");
    }

    public function approveAttendanceException(Request $request, Attendance $attendance): RedirectResponse
    {
        $teacher = $request->user()->teacher;
        abort_if($teacher === null || $attendance->placement->teacher_id !== $teacher->id, 403);

        $attendance->update([
            'approved_by_teacher_id' => $teacher->id,
            'review_status' => $attendance->review_status ? 'confirmed' : null,
            'reviewed_at' => now(),
        ]);
        AuditLog::record('attendance.exception_approved', $attendance, ['date' => $attendance->attendance_date->toDateString()]);

        return back()->with('status', "Pengecualian absensi {$attendance->student->user->name} telah disetujui.");
    }

    public function assessments(Request $request): View
    {
        $teacher = $request->user()->teacher;
        abort_if($teacher === null, 403);

        $placements = Placement::query()
            ->with(['student.user', 'company', 'pklPeriod', 'assessment.versions', 'mentorSignoffs'])
            ->where('teacher_id', $teacher->id)
            ->latest()
            ->get();

        return view('teacher.assessments', [
            'school' => $request->user()->school,
            'teacher' => $teacher,
            'placements' => $placements,
        ]);
    }

    public function saveAssessment(
        Request $request,
        Placement $placement,
        CalculateFinalScoreAction $action
    ): RedirectResponse {
        $teacher = $request->user()->teacher;
        abort_if($teacher === null || $placement->teacher_id !== $teacher->id, 403);

        $validated = $request->validate([
            'industry_scores' => ['required', 'array'],
            'industry_scores.*' => ['required', 'numeric', 'min:0', 'max:100'],
            'school_scores' => ['required', 'array'],
            'school_scores.*' => ['required', 'numeric', 'min:0', 'max:100'],
            'signed_by_name' => ['nullable', 'string', 'max:120'],
            'industry_notes' => ['nullable', 'string', 'max:500'],
            'teacher_notes' => ['nullable', 'string', 'max:500'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'industry_form_photo' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'finalize' => ['nullable', 'boolean'],
            'industry_source' => ['nullable', 'in:manual_form,mentor_access'],
        ]);
        $source = $validated['industry_source'] ?? 'manual_form';
        if ($source === 'mentor_access' && ! \App\Models\MentorEvaluation::query()->where('placement_id', $placement->id)->where('status', 'final')->exists()) {
            $source = 'manual_form';
        }

        $photoPath = null;
        if ($request->hasFile('industry_form_photo')) {
            $photoPath = MediaStorage::storeStudentUpload(
                $placement->school_id,
                'assessments',
                $request->file('industry_form_photo')
            );
        }

        try {
            $assessment = $action->handle(
                placement: $placement,
                teacher: $teacher,
                industryScores: $validated['industry_scores'],
                schoolScores: $validated['school_scores'],
                industryFormPhotoPath: $photoPath,
                signedByName: $validated['signed_by_name'] ?? null,
                industryNotes: $validated['industry_notes'] ?? null,
                teacherNotes: $validated['teacher_notes'] ?? null,
                certificateNumber: $validated['certificate_number'] ?? null,
                finalize: $request->boolean('finalize'),
                industrySource: $source,
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['assessment' => $e->getMessage()]);
        }

        AuditLog::record($assessment->finalized_at !== null ? 'assessment.finalized' : 'assessment.saved', $assessment, [
            'student' => $placement->student->user->name,
            'final_score' => $assessment->final_score,
        ]);

        $msg = $assessment->finalized_at !== null
            ? "Nilai {$placement->student->user->name} difinalisasi ({$assessment->final_score} - {$assessment->predicate}). PDF siap dicetak."
            : "Draf nilai {$placement->student->user->name} disimpan ({$assessment->final_score}).";

        return back()->with('status', $msg);
    }
}
