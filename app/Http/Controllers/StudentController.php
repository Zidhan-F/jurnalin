<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Attendance\RecordCheckInAction;
use App\Actions\Attendance\RecordCheckOutAction;
use App\Actions\Journal\SubmitJournalAction;
use App\Enums\AttendanceStatus;
use App\Enums\JournalStatus;
use App\Enums\PlacementStatus;
use App\Models\Attendance;
use App\Models\Journal;
use App\Models\Placement;
use App\Support\MediaStorage;
use App\ValueObjects\Coordinates;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class StudentController extends Controller
{
    public function home(Request $request): View
    {
        $student = $request->user()->student;
        abort_if($student === null, 403, 'Profil siswa tidak ditemukan.');

        $school = $request->user()->school;
        $nowLocal = CarbonImmutable::now($school->timezone);
        $todayDate = $nowLocal->toDateString();

        $placement = Placement::query()
            ->with(['company', 'teacher.user', 'pklPeriod'])
            ->where('student_id', $student->id)
            ->where('status', PlacementStatus::Active)
            ->first();

        $todayAttendance = null;
        $todayJournal = null;

        if ($placement !== null) {
            $todayAttendance = Attendance::query()
                ->where('placement_id', $placement->id)
                ->whereDate('attendance_date', $todayDate)
                ->first();

            $todayJournal = Journal::query()
                ->where('placement_id', $placement->id)
                ->whereDate('journal_date', $todayDate)
                ->latest()
                ->first();
        }

        return view('student.home', [
            'school' => $school,
            'student' => $student,
            'placement' => $placement,
            'todayAttendance' => $todayAttendance,
            'todayJournal' => $todayJournal,
            'todayDate' => $todayDate,
            'nowTime' => $nowLocal->format('H:i'),
            'trackingAllowed' => $student->trackingAllowed(),
            'isScheduledToday' => $placement?->pklPeriod->isScheduledDay($nowLocal) ?? false,
            'holidayToday' => $placement ? collect($placement->pklPeriod->holidayList())->firstWhere('date', $todayDate) : null,
            'attendedDays' => $placement ? Attendance::query()->where('placement_id', $placement->id)
                ->whereIn('status', [\App\Enums\AttendanceStatus::Present, \App\Enums\AttendanceStatus::Late])->count() : 0,
            'recentJournals' => $placement ? Journal::query()->where('placement_id', $placement->id)
                ->where('status', '!=', JournalStatus::Draft)->latest('journal_date')->limit(3)->get() : collect(),
            'revisions' => Journal::query()->where('student_id', $student->id)->where('status', JournalStatus::RevisionNeeded)->orderBy('journal_date')->get(),
            'pendingLeaves' => \App\Models\LeaveRequest::query()->where('student_id', $student->id)->where('status', 'submitted')->count(),
            'mentorNotes' => $placement ? \App\Models\MentoringNote::query()->where('placement_id', $placement->id)->where('visibility', 'shared')->where('status', 'open')->latest()->limit(3)->get() : collect(),
            'openTargets' => $placement ? \App\Models\LearningTarget::query()->where('placement_id', $placement->id)->where('status', 'open')->orderBy('due_date')->limit(3)->get() : collect(),
        ]);
    }

    public function checkIn(Request $request, RecordCheckInAction $action): RedirectResponse
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);

        $placement = Placement::query()
            ->with(['company', 'school', 'pklPeriod', 'student'])
            ->where('student_id', $student->id)
            ->where('status', PlacementStatus::Active)
            ->firstOrFail();

        $mode = $request->string('mode')->toString();
        $isLeave = in_array($mode, ['sick', 'permission'], true);
        $needsLocation = ! $isLeave && $placement->pklPeriod->require_location && $student->trackingAllowed();

        $validated = $request->validate([
            'photo' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'photo_data_url' => ['nullable', 'string', 'max:6000000'],
            'exception_reason' => ['nullable', 'string', 'max:300'],
            'device_id' => ['nullable', 'string', 'max:64'],
            ...($needsLocation ? [
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'accuracy' => ['nullable', 'integer', 'min:0'],
            ] : []),
        ]);

        // Swafoto tidak disimpan sama sekali tanpa persetujuan wali (S13); surat izin tetap boleh.
        $photoPath = ! $isLeave && ! $student->trackingAllowed() ? null : MediaStorage::storeStudentUpload(
            schoolId: $placement->school_id,
            category: 'attendances',
            file: $request->file('photo'),
            base64DataUrl: $validated['photo_data_url'] ?? null,
        );

        try {
            // Savepoint: bila insert gagal (klik ganda), transaksi request tetap bisa dipakai.
            DB::transaction(fn () => $isLeave
                ? $action->handle(
                    placement: $placement,
                    photoPath: $photoPath,
                    exceptionReason: $validated['exception_reason'] ?? '',
                    leaveStatus: AttendanceStatus::from($mode),
                )
                : $action->handle(
                    placement: $placement,
                    studentCoordinates: $needsLocation ? new Coordinates((float) $validated['latitude'], (float) $validated['longitude']) : null,
                    gpsAccuracyMeters: isset($validated['accuracy']) ? (int) $validated['accuracy'] : null,
                    photoPath: $photoPath,
                    isOutsideDuty: $request->boolean('is_outside_duty'),
                    exceptionReason: $validated['exception_reason'] ?? null,
                    deviceHash: ! empty($validated['device_id']) ? hash('sha256', $validated['device_id'].'|'.$request->userAgent()) : null,
                ));
        } catch (DomainException $e) {
            MediaStorage::discard($photoPath);

            return back()->withInput()->withErrors(['attendance' => $e->getMessage()]);
        } catch (UniqueConstraintViolationException) {
            MediaStorage::discard($photoPath);

            return back()->withErrors(['attendance' => 'Anda sudah mencatat kehadiran untuk hari ini.']);
        }

        return back()->with('status', 'Kehadiran hari ini berhasil dicatat.');
    }

    public function checkOut(Request $request, RecordCheckOutAction $action): RedirectResponse
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);

        $school = $request->user()->school;
        $todayDate = CarbonImmutable::now($school->timezone)->toDateString();

        $placement = Placement::query()
            ->where('student_id', $student->id)
            ->where('status', PlacementStatus::Active)
            ->firstOrFail();

        $attendance = Attendance::query()
            ->where('placement_id', $placement->id)
            ->whereDate('attendance_date', $todayDate)
            ->firstOrFail();

        $needsLocation = $placement->pklPeriod->require_location && $student->trackingAllowed();
        $validated = $request->validate($needsLocation ? [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ] : []);

        try {
            $action->handle(
                attendance: $attendance,
                studentCoordinates: $needsLocation ? new Coordinates((float) $validated['latitude'], (float) $validated['longitude']) : null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['attendance' => $e->getMessage()]);
        }

        return back()->with('status', 'Absen pulang berhasil dicatat. Terima kasih atas kerja keras Anda hari ini.');
    }

    public function journals(Request $request): View
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);

        $school = $request->user()->school;
        $selection = $request->validate(['placement' => ['nullable', 'uuid'], 'edit' => ['nullable', 'uuid']]);
        $placements = $this->journalPlacements($request, $student->id)->with(['company', 'pklPeriod'])->get();
        $placement = isset($selection['placement']) ? $placements->findOrFail($selection['placement']) : $placements->first();

        $editingJournal = null;
        if ($request->filled('edit')) {
            $editingJournal = Journal::query()
                ->where('student_id', $student->id)
                ->whereKey($request->string('edit')->toString())
                ->first();
            if ($editingJournal) {
                $placement = $placements->findOrFail($editingJournal->placement_id);
            }
        }

        $journals = Journal::query()
            ->with('validatedByTeacher.user')
            ->where('student_id', $student->id)
            ->orderByDesc('journal_date')
            ->orderByDesc('created_at')
            ->get();

        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,submitted,revision_needed,approved'], 'from' => ['nullable', 'date'], 'until' => ['nullable', 'date']]);
        $journals = $journals->when($filters['q'] ?? null, fn ($c, $q) => $c->filter(fn ($j) => str_contains(mb_strtolower($j->activity_title.' '.$j->activity_description), mb_strtolower($q))))
            ->when($filters['status'] ?? null, fn ($c, $s) => $c->filter(fn ($j) => $j->status->value === $s))
            ->when($filters['from'] ?? null, fn ($c, $d) => $c->filter(fn ($j) => $j->journal_date->toDateString() >= $d))
            ->when($filters['until'] ?? null, fn ($c, $d) => $c->filter(fn ($j) => $j->journal_date->toDateString() <= $d));

        return view('student.journals', [
            'school' => $school,
            'placement' => $placement,
            'journals' => $journals,
            'editingJournal' => $editingJournal,
            'placements' => $placements,
            'todayDate' => CarbonImmutable::now($school->timezone)->toDateString(),
            'earliestDate' => $placement ? max($placement->pklPeriod->start_date->toDateString(), CarbonImmutable::now($school->timezone)->subDays($placement->pklPeriod->backdate_days)->toDateString()) : null,
            'templates' => \App\Support\JournalTemplates::forMajor($student->major),
            'recent' => \App\Support\JournalTemplates::recent($student->id),
            'objectives' => \App\Support\LearningObjectives::forSchool($school),
            'maxPhotos' => max(1, (int) $school->max_journal_photos),
            'filters' => $filters,
        ]);
    }

    public function storeJournal(Request $request, SubmitJournalAction $action): RedirectResponse
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);

        $target = $request->validate(['journal_id' => ['nullable', 'uuid'], 'placement_id' => ['nullable', 'uuid']]);
        $journalId = $target['journal_id'] ?? null;
        $existing = $journalId ? Journal::where('student_id', $student->id)->findOrFail($journalId) : null;
        $placementId = $existing?->placement_id ?? $target['placement_id'] ?? null;
        $placement = $this->journalPlacements($request, $student->id)
            ->with(['pklPeriod', 'school', 'teacher.user'])
            ->when($placementId, fn ($q) => $q->whereKey($placementId))
            ->firstOrFail();

        $now = CarbonImmutable::now($placement->school->timezone);
        $today = $now->toDateString();
        $earliest = $existing ? $placement->pklPeriod->start_date->toDateString() : max($placement->pklPeriod->start_date->toDateString(), $now->subDays($placement->pklPeriod->backdate_days)->toDateString());
        $maxPhotos = max(1, (int) $placement->school->max_journal_photos);

        $validated = $request->validate([
            'journal_id' => ['nullable', 'uuid'],
            'journal_date' => ['required', 'date', 'before_or_equal:'.$today, 'after_or_equal:'.$earliest],
            'obstacles' => ['nullable', 'string', 'max:1000'],
            'template_key' => ['nullable', 'string', 'max:64'],
            'confirmed_done' => [$request->input('submit_action') === 'submitted' ? 'accepted' : 'nullable'],
            'photo_data_urls' => ['nullable', 'array', 'max:'.$maxPhotos],
            'photo_data_urls.*' => ['nullable', 'string', 'max:6000000'],
            'remove_photos' => ['nullable', 'array'],
            'remove_photos.*' => ['string'],
            'objective_ids' => ['nullable', 'array'],
            'objective_ids.*' => ['uuid'],
            'work_unit' => ['required', 'string', 'max:100'],
            'activity_title' => ['required', 'string', 'max:200'],
            'activity_description' => ['required', 'string', 'min:15', 'max:3000'],
            'tools_materials' => ['nullable', 'string', 'max:500'],
            'learning_outcome' => ['nullable', 'string', 'max:500'],
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:960'],
            'submit_action' => ['required', 'in:draft,submitted'],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'photo_data_url' => ['nullable', 'string'],
        ], [
            'confirmed_done.accepted' => 'Centang konfirmasi bahwa kegiatan ini benar-benar Anda lakukan sebelum mengirim.',
            'journal_date.after_or_equal' => 'Jurnal untuk tanggal lampau hanya bisa diisi sampai '.$placement->pklPeriod->backdate_days.' hari ke belakang.',
            'photo_data_urls.max' => 'Maksimal '.$maxPhotos.' foto per jurnal.',
        ]);

        $photoPath = MediaStorage::storeStudentUpload(
            schoolId: $placement->school_id,
            category: 'journals',
            file: $request->file('photo'),
            base64DataUrl: $validated['photo_data_url'] ?? null,
        );
        $extra = collect($validated['photo_data_urls'] ?? [])->filter()->map(fn ($d) => MediaStorage::storeStudentUpload($placement->school_id, 'journals', null, $d))->filter()->values()->all();
        $kept = count(array_diff($existing?->photos() ?? [], $validated['remove_photos'] ?? []));
        if ($kept + count($extra) + ($photoPath ? 1 : 0) > $maxPhotos) {
            array_map([MediaStorage::class, 'discard'], [...$extra, $photoPath]);

            return back()->withInput()->withErrors(['photo_data_urls' => 'Maksimal '.$maxPhotos.' foto per jurnal. Hapus foto lama terlebih dahulu.']);
        }

        try {
            $journal = $action->handle(
                placement: $placement,
                data: [...$validated, 'photo_path' => $photoPath, 'extra_photo_paths' => $extra, 'confirmed_done' => $request->boolean('confirmed_done')],
                targetStatus: JournalStatus::from($validated['submit_action']),
                existingJournal: $existing,
            );
        } catch (DomainException $e) {
            array_map([MediaStorage::class, 'discard'], [...$extra, $photoPath]);

            return back()->withInput()->withErrors(['journal' => $e->getMessage()]);
        }
        \App\Support\LearningObjectives::claim($journal, $validated['objective_ids'] ?? []);
        if ($journal->status === JournalStatus::Submitted) {
            \App\Support\Notify::user($placement->teacher?->user, 'journal.submitted', 'Jurnal baru dari '.$request->user()->name, $journal->activity_title, '/teacher/review');
        }

        $msg = $journal->status === JournalStatus::Submitted
            ? 'Jurnal kegiatan berhasil dikirim ke guru pembimbing.'
            : 'Jurnal disimpan sebagai draf.';

        return redirect('/student/journals')->with('status', $msg);
    }

    private function journalPlacements(Request $request, string $studentId): Builder
    {
        return Placement::where('student_id', $studentId)
            ->where(fn ($q) => $q->where('status', PlacementStatus::Active)->orWhere(fn ($q) => $q
                ->where('status', PlacementStatus::Completed)->whereKey($request->attributes->get('grace_placements', []))))
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->latest();
    }

    public function profile(Request $request): View
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);

        $placement = Placement::query()
            ->with(['company', 'teacher.user', 'pklPeriod', 'assessment'])
            ->where('student_id', $student->id)
            ->latest()
            ->first();

        $attendedCount = 0;
        $approvedJournalsCount = 0;

        if ($placement !== null) {
            $attendedCount = $placement->attendances()
                ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
                ->count();

            $approvedJournalsCount = $placement->journals()
                ->where('status', JournalStatus::Approved->value)
                ->count();
        }

        return view('student.profile', [
            'school' => $request->user()->school,
            'student' => $student,
            'placement' => $placement,
            'attendedCount' => $attendedCount,
            'approvedJournalsCount' => $approvedJournalsCount,
        ]);
    }
}
