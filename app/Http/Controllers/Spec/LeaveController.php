<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Actions\Assessment\CalculateFinalScoreAction;
use App\Enums\AttendanceStatus;
use App\Enums\JournalStatus;
use App\Enums\PlacementStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Journal;
use App\Models\LeaveRequest;
use App\Models\Placement;
use App\Support\MediaStorage;
use App\Support\Notify;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Izin/sakit resmi (P09), tinjauan presensi bertanda (P37), pembukaan ulang jurnal (P11),
 * dan revisi nilai final berversi (P39). Semua keputusan hanya oleh guru yang ditugaskan atau admin.
 */
final class LeaveController extends Controller
{
    public function studentIndex(Request $request): View
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);
        $placement = Placement::query()->with(['pklPeriod', 'company'])->where('student_id', $student->id)->where('status', PlacementStatus::Active)->first();

        return view('spec.student.leaves', [
            'placement' => $placement,
            'leaves' => LeaveRequest::query()->where('student_id', $student->id)->latest()->get(),
            'today' => CarbonImmutable::now($request->user()->school->timezone)->toDateString(),
        ]);
    }

    public function studentStore(Request $request): RedirectResponse
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);
        $placement = Placement::query()->with(['pklPeriod', 'teacher.user', 'student.user'])->where('student_id', $student->id)->where('status', PlacementStatus::Active)->firstOrFail();
        $period = $placement->pklPeriod;
        $earliest = CarbonImmutable::now($request->user()->school->timezone)->subDays($period->backdate_days)->toDateString();

        $data = $request->validate([
            'type' => ['required', 'in:sick,permission'],
            'start_date' => ['required', 'date', 'after_or_equal:'.max($earliest, $period->start_date->toDateString()), 'before_or_equal:'.$period->end_date->toDateString()],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:'.$period->end_date->toDateString()],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'attachment' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], ['start_date.after_or_equal' => 'Pengajuan untuk tanggal lampau hanya sampai '.$period->backdate_days.' hari ke belakang.']);

        $overlap = LeaveRequest::query()->where('placement_id', $placement->id)->whereIn('status', ['submitted', 'approved'])
            ->where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date'])->exists();
        if ($overlap) {
            return back()->withInput()->withErrors(['start_date' => 'Sudah ada pengajuan izin pada rentang tanggal itu.']);
        }

        $leave = LeaveRequest::query()->create([
            'school_id' => $placement->school_id,
            'placement_id' => $placement->id,
            'student_id' => $student->id,
            'type' => $data['type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'],
            'attachment_path' => MediaStorage::storeStudentUpload($placement->school_id, 'leaves', $request->file('attachment')),
        ]);
        AuditLog::record('leave.submitted', $leave, ['from' => $data['start_date'], 'until' => $data['end_date']]);
        Notify::user($placement->teacher->user, 'leave.submitted', 'Pengajuan '.($data['type'] === 'sick' ? 'sakit' : 'izin').' dari '.$placement->student->user->name, null, '/teacher#izin');

        return redirect()->route('student.leaves')->with('status', 'Pengajuan terkirim ke guru pembimbing. Anda akan menerima notifikasi setelah diputuskan.');
    }

    public function decide(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $teacher = $request->user()->teacher;
        abort_unless($teacher !== null && $leave->placement->teacher_id === $teacher->id, 403);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'decision_note' => ['required_if:decision,rejected', 'nullable', 'string', 'max:500'],
        ], ['decision_note.required_if' => 'Tulis alasan saat menolak pengajuan.']);

        if ($leave->status !== 'submitted') {
            return back()->withErrors(['decision' => 'Pengajuan ini sudah diputuskan.']);
        }

        $leave->update(['status' => $data['decision'], 'decision_note' => $data['decision_note'] ?? null, 'decided_by_user_id' => $request->user()->id, 'decided_at' => now()]);

        if ($data['decision'] === 'approved') {
            $period = $leave->placement->pklPeriod;
            $status = AttendanceStatus::from($leave->type);
            for ($d = CarbonImmutable::parse($leave->start_date); $d <= CarbonImmutable::parse($leave->end_date); $d = $d->addDay()) {
                if (! $period->isScheduledDay($d)) {
                    continue;
                }
                $existing = Attendance::query()->where('placement_id', $leave->placement_id)->whereDate('attendance_date', $d->toDateString())->first();
                if ($existing && $existing->status->countsTowardAttendanceScore()) {
                    continue; // Presensi hadir yang nyata tidak ditimpa.
                }
                Attendance::query()->updateOrCreate(
                    ['school_id' => $leave->school_id, 'placement_id' => $leave->placement_id, 'attendance_date' => $d->toDateString()],
                    ['student_id' => $leave->student_id, 'status' => $status, 'is_within_radius' => true, 'exception_reason' => $leave->reason,
                        'approved_by_teacher_id' => $teacher->id, 'leave_request_id' => $leave->id, 'review_status' => 'confirmed', 'reviewed_at' => now()],
                );
            }
        }

        AuditLog::record('leave.'.$data['decision'], $leave, ['student' => $leave->student->user->name]);
        Notify::user($leave->student->user, 'leave.'.$data['decision'], 'Pengajuan izin '.$leave->start_date->format('d/m').($data['decision'] === 'approved' ? ' disetujui' : ' ditolak'), $data['decision_note'] ?? null, '/student/izin');

        return back()->with('status', 'Pengajuan '.$leave->student->user->name.($data['decision'] === 'approved' ? ' disetujui dan presensi diperbarui.' : ' ditolak.'));
    }

    public function reviewAttendance(Request $request, Attendance $attendance): RedirectResponse
    {
        $teacher = $request->user()->teacher;
        abort_unless($teacher !== null && $attendance->placement->teacher_id === $teacher->id, 403);
        $data = $request->validate([
            'decision' => ['required', 'in:confirmed,rejected'],
            'review_note' => ['required_if:decision,rejected', 'nullable', 'string', 'max:300'],
        ], ['review_note.required_if' => 'Tulis alasan bila presensi tidak diakui.']);

        $attendance->update([
            'review_status' => $data['decision'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_at' => now(),
            'approved_by_teacher_id' => $teacher->id,
            // Tanda anomali tidak otomatis menjadi sanksi: hanya keputusan guru yang mengubah status menjadi Alpa.
            'status' => $data['decision'] === 'rejected' ? AttendanceStatus::Absent : $attendance->status,
        ]);
        AuditLog::record('attendance.reviewed', $attendance, ['decision' => $data['decision'], 'date' => $attendance->attendance_date->toDateString()]);

        return back()->with('status', 'Presensi '.$attendance->student->user->name.' tanggal '.$attendance->attendance_date->format('d/m').($data['decision'] === 'confirmed' ? ' dikonfirmasi.' : ' tidak diakui (Alpa).'));
    }

    public function reopenJournal(Request $request, Journal $journal): RedirectResponse
    {
        $user = $request->user();
        $allowed = $user->role === UserRole::SchoolAdmin || ($user->teacher && $journal->placement->teacher_id === $user->teacher->id);
        abort_unless($allowed, 403);
        $reason = trim((string) $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']])['reason']);
        if ($journal->status !== JournalStatus::Approved) {
            return back()->withErrors(['reason' => 'Hanya jurnal yang sudah disetujui yang perlu dibuka ulang.']);
        }

        $journal->forceFill(['status' => JournalStatus::RevisionNeeded, 'reopened_reason' => $reason, 'reopened_at' => now(), 'teacher_feedback' => $reason])->save();
        $journal->recordVersion('Dibuka ulang: '.$reason);
        AuditLog::record('journal.reopened', $journal, ['date' => $journal->journal_date->toDateString(), 'reason' => $reason]);
        Notify::user($journal->student->user, 'journal.reopened', 'Jurnal '.$journal->journal_date->format('d/m/Y').' dibuka ulang untuk diperbaiki', $reason, '/student/journals?edit='.$journal->id);

        return back()->with('status', 'Jurnal dibuka ulang dengan alasan tercatat. Siswa dapat memperbaikinya.');
    }

    public function openAssessmentRevision(Request $request, Placement $placement, CalculateFinalScoreAction $action): RedirectResponse
    {
        $teacher = $request->user()->teacher;
        abort_unless($teacher !== null && $placement->teacher_id === $teacher->id, 403);
        $assessment = Assessment::query()->where('placement_id', $placement->id)->firstOrFail();

        try {
            $action->openRevision($assessment, (string) $request->input('reason'));
        } catch (DomainException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }
        AuditLog::record('assessment.revision_opened', $assessment, ['version' => $assessment->fresh()->version, 'reason' => $request->input('reason')]);

        return back()->with('status', 'Revisi nilai versi '.$assessment->fresh()->version.' dibuka. Snapshot versi sebelumnya tetap tersimpan.');
    }
}
