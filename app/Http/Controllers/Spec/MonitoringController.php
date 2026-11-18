<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\JournalStatus;
use App\Enums\PlacementStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Journal;
use App\Models\LearningTarget;
use App\Models\MentoringNote;
use App\Models\PklPeriod;
use App\Models\Placement;
use App\Models\WeeklyReflection;
use App\Models\WorkProof;
use App\Support\LearningObjectives;
use App\Support\PklMonitor;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Review Inbox (P11/P12/P16), detail peserta, kalender (P10), kelengkapan (P18), peringatan (P19), kebijakan periode (P03/P04). */
final class MonitoringController extends Controller
{
    public function reviewInbox(Request $request): View
    {
        $teacher = $request->user()->teacher;
        abort_if($teacher === null, 403);
        $filters = $request->validate([
            'placement' => ['nullable', 'uuid'],
            'status' => ['nullable', 'in:submitted,revision_needed,approved,draft'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date'],
        ]);
        $placements = Placement::query()->with('student.user')->where('teacher_id', $teacher->id)->get();

        $journals = Journal::query()->with(['student.user', 'placement.company', 'objectives'])
            ->whereIn('placement_id', $placements->pluck('id'))
            ->where('status', $filters['status'] ?? JournalStatus::Submitted->value)
            ->when($filters['placement'] ?? null, fn ($q, $id) => $q->where('placement_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('journal_date', '>=', $d))
            ->when($filters['until'] ?? null, fn ($q, $d) => $q->whereDate('journal_date', '<=', $d))
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('activity_title', 'ilike', "%{$s}%")->orWhere('activity_description', 'ilike', "%{$s}%")))
            ->orderBy('journal_date')->limit(100)->get();

        $today = CarbonImmutable::now($request->user()->school->timezone);
        $schedule = \App\Support\ReviewSchedule::forTeacher($teacher, $today);
        $frequency = $schedule['frequency'];
        $placements->load('pklPeriod');
        $bucketOf = fn ($j) => \App\Support\ReviewSchedule::bucket($j->journal_date, $frequency, $placements->firstWhere('id', $j->placement_id)?->pklPeriod->start_date);

        return view('spec.teacher.review', [
            'journals' => $journals,
            'groups' => $journals->groupBy(fn ($j) => $bucketOf($j)['key'])->map(fn ($items) => ['label' => $bucketOf($items->first())['label'], 'items' => $items]),
            'frequency' => $frequency,
            'isDue' => $schedule['due'],
            'nextCheck' => $schedule['next'],
            'lastReviewed' => $teacher->last_reviewed_at,
            'placements' => $placements,
            'filters' => $filters,
            'commentTemplates' => [
                'Jelaskan urutan langkah kerja sesuai SOP tempat PKL.',
                'Tambahkan hasil pekerjaan dan kendala yang ditemui.',
                'Lampirkan foto dokumentasi yang tidak memuat data pelanggan.',
                'Tuliskan alat atau sistem yang dipakai.',
            ],
        ]);
    }

    public function updateReviewFrequency(Request $request): RedirectResponse
    {
        $teacher = $request->user()->teacher;
        abort_if($teacher === null, 403);
        $data = $request->validate(['review_frequency' => ['required', 'in:'.implode(',', array_keys(\App\Support\ReviewSchedule::FREQUENCIES))]]);
        $teacher->update($data);
        AuditLog::record('teacher.review_frequency', $teacher, $data);

        return back()->with('status', 'Jadwal periksa jurnal: '.mb_strtolower(\App\Support\ReviewSchedule::label($data['review_frequency'])).'. Siswa melihat jadwal ini di halaman jurnal mereka.');
    }

    public function period(PklPeriod $period): View
    {
        $placements = $period->placements()->with(['student.user', 'teacher.user', 'company', 'assessment', 'group'])
            ->withCount(['attendances', 'journals'])->orderBy('created_at')->orderBy('id')->paginate(25);
        $groups = \App\Models\BillingGroup::where('pkl_period_id', $period->id)->with('members.user')->orderBy('name')->get();

        $classes = \App\Models\PeriodClass::query()->where('pkl_period_id', $period->id)->with('members.user')->orderBy('name')->get();
        $classOf = $classes->flatMap(fn ($c) => $c->members->mapWithKeys(fn ($s) => [$s->id => $c->name]));
        $active = Placement::query()->with('company')->where('pkl_period_id', $period->id)->where('status', \App\Enums\PlacementStatus::Active)->get()->keyBy('student_id');
        $allStudents = \App\Models\Student::query()->with('user')->get()->sortBy(fn ($s) => $s->class_name.' '.$s->user->name)->values();
        // Sumber anggota grup: siswa di kelas periode ini; bila belum ada kelas, semua siswa.
        $pool = ($classes->isEmpty() ? $allStudents : $allStudents->filter(fn ($s) => $classOf->has($s->id)))
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->user->name, 'class' => $classOf[$s->id] ?? $s->class_name,
                'placement' => $active[$s->id] ?? null])->values();

        return view('spec.period', [
            'period' => $period, 'placements' => $placements, 'groups' => $groups, 'classes' => $classes,
            'tab' => in_array(request('tab'), ['kelas', 'grup', 'penempatan'], true) ? request('tab') : 'kelas',
            'classOptions' => $allStudents->pluck('class_name')->filter()->unique()->diff($classes->pluck('name'))->sort()->values(),
            'unclassed' => $allStudents->reject(fn ($s) => $classOf->has($s->id))->values(),
            'placementGroups' => \App\Models\PlacementGroup::query()->where('pkl_period_id', $period->id)
                ->with(['company', 'teacher.user', 'placements' => fn ($q) => $q->where('status', \App\Enums\PlacementStatus::Active)->with('student.user')])
                ->orderBy('name')->get(),
            'pool' => $pool,
            'ungrouped' => $pool->filter(fn ($p) => $p['placement'] === null || $p['placement']->placement_group_id === null)->values(),
            'poolClasses' => $pool->pluck('class')->filter()->unique()->sort()->values(),
            'companies' => \App\Models\Company::query()->orderBy('name')->get(['id', 'name']),
            'teachers' => \App\Models\Teacher::query()->with('user')->withCount(['placements as active_load' => fn ($q) => $q->where('status', \App\Enums\PlacementStatus::Active)])->get()->sortBy('user.name')->values(),
        ]);
    }

    public function participant(Request $request, Placement $placement): View
    {
        $this->authorizePlacement($request, $placement);
        $placement->load(['student.user', 'company', 'pklPeriod', 'teacher.user', 'assessment', 'school']);
        $school = $placement->school;
        $today = CarbonImmutable::now($school->timezone);
        $month = $request->filled('bulan') ? CarbonImmutable::parse($request->string('bulan').'-01') : $today;

        return view('spec.participant', [
            'placement' => $placement,
            'student' => $placement->student,
            'calendar' => PklMonitor::calendar($placement, $month, $today->toDateString()),
            'month' => $month,
            'readiness' => PklMonitor::readiness($placement, $today->toDateString()),
            'objectives' => LearningObjectives::progress($school, $placement->id),
            'targets' => LearningTarget::query()->where('placement_id', $placement->id)->latest()->get(),
            'notes' => MentoringNote::query()->where('placement_id', $placement->id)->latest()->get(),
            'reflections' => WeeklyReflection::query()->where('placement_id', $placement->id)->latest('week_start')->get(),
            'proofs' => WorkProof::query()->where('placement_id', $placement->id)->latest()->get(),
            'claims' => Journal::query()->with('objectives')->where('placement_id', $placement->id)->whereHas('objectives', fn ($q) => $q->where('journal_objectives.status', 'claimed'))->latest('journal_date')->limit(20)->get(),
            'approvedJournals' => Journal::query()->where('placement_id', $placement->id)->where('status', JournalStatus::Approved)->latest('journal_date')->limit(10)->get(),
            'consent' => $placement->student->activeConsent(),
            'canAct' => $request->user()->role === UserRole::SchoolAdmin || $placement->teacher_id === $request->user()->teacher?->id,
        ]);
    }

    public function alerts(Request $request): View
    {
        $user = $request->user();
        $placements = Placement::query()->with(['student.user', 'company', 'pklPeriod'])->where('status', PlacementStatus::Active)
            ->when($user->role === UserRole::Teacher, fn ($q) => $q->where('teacher_id', $user->teacher?->id))->get();

        return view('spec.alerts', ['alerts' => PklMonitor::alerts($placements, CarbonImmutable::now($user->school->timezone)->toDateString())]);
    }

    public function studentCalendar(Request $request): View
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);
        $placement = Placement::query()->with(['pklPeriod', 'company', 'assessment'])->where('student_id', $student->id)->whereIn('status', [PlacementStatus::Active, PlacementStatus::Completed])->latest()->first();
        $today = CarbonImmutable::now($request->user()->school->timezone);
        $month = $request->filled('bulan') ? CarbonImmutable::parse($request->string('bulan').'-01') : $today;

        return view('spec.student.calendar', [
            'placement' => $placement,
            'calendar' => $placement ? PklMonitor::calendar($placement, $month, $today->toDateString()) : [],
            'readiness' => $placement ? PklMonitor::readiness($placement, $today->toDateString()) : [],
            'month' => $month,
        ]);
    }

    public function updatePeriodPolicy(Request $request, PklPeriod $period): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,scheduled,active,completed,cancelled'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['integer', 'between:1,7'],
            'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'backdate_days' => ['required', 'integer', 'min:0', 'max:60'],
            'holidays_text' => ['nullable', 'string', 'max:5000'],
            'aspects_text' => ['nullable', 'string', 'max:5000'],
        ]);

        $holidays = collect(preg_split('/\r?\n/', (string) ($data['holidays_text'] ?? '')))
            ->map(fn ($line) => array_map('trim', explode('|', $line, 2)))
            ->filter(fn ($p) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $p[0] ?? ''))
            ->map(fn ($p) => ['date' => $p[0], 'label' => $p[1] ?? 'Libur'])->values()->all();

        $template = $period->assessment_template;
        if (trim((string) ($data['aspects_text'] ?? '')) !== '') {
            $groups = ['industry' => [], 'school' => []];
            foreach (preg_split('/\r?\n/', $data['aspects_text']) as $line) {
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) === 4 && in_array($parts[0], ['industri', 'sekolah'], true)) {
                    $groups[$parts[0] === 'industri' ? 'industry' : 'school'][] = ['code' => $parts[1], 'label' => $parts[2], 'weight' => (int) $parts[3]];
                }
            }
            $template['industry_aspects'] = $groups['industry'];
            $template['school_aspects'] = $groups['school'];
            try {
                \App\ValueObjects\AssessmentRubric::fromArray($template);
            } catch (\InvalidArgumentException $e) {
                return back()->withInput()->withErrors(['aspects_text' => $e->getMessage()]);
            }
            if ($period->placements()->whereHas('assessment', fn ($q) => $q->whereNotNull('finalized_at'))->exists()) {
                return back()->withErrors(['aspects_text' => 'Sudah ada nilai final pada periode ini. Ubah aspek pada periode baru agar nilai historis tidak berubah makna.']);
            }
        }

        $period->update([
            'status' => $data['status'],
            'is_active' => in_array($data['status'], ['scheduled', 'active'], true),
            'work_days' => array_values(array_unique(array_map('intval', $data['work_days']))),
            'late_tolerance_minutes' => $data['late_tolerance_minutes'],
            'require_location' => $request->boolean('require_location'),
            'require_selfie' => $request->has('require_selfie') ? $request->boolean('require_selfie') : null,
            'backdate_days' => (int) $data['backdate_days'],
            'holidays' => $holidays,
            'assessment_template' => $template,
        ]);
        AuditLog::record('period.policy_updated', $period, ['status' => $data['status']]);

        return back()->with('status', "Kebijakan periode {$period->name} diperbarui.");
    }

    public function updatePlacementLocation(Request $request, Placement $placement): RedirectResponse
    {
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'geofence_radius_m' => ['nullable', 'integer', 'min:30', 'max:5000'],
            'status' => ['nullable', 'in:offered,confirmed'],
        ]);
        $updates = collect($data)->only(['latitude', 'longitude', 'geofence_radius_m'])->all();
        if (! empty($data['status'])) {
            $next = PlacementStatus::from($data['status']);
            if (! $placement->status->canTransitionTo($next)) {
                return back()->withErrors(['status' => "Status {$placement->status->label()} tidak dapat diubah ke {$next->label()}."]);
            }
            $updates['status'] = $next;
        }
        $placement->update($updates);
        AuditLog::record('placement.location_updated', $placement, $updates);

        return back()->with('status', 'Lokasi presensi dan status penempatan diperbarui. Riwayat perubahan tercatat.');
    }

    private function authorizePlacement(Request $request, Placement $placement): void
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::SchoolAdmin || $placement->teacher_id === $user->teacher?->id, 403);
    }
}
