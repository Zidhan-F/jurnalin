<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\JournalStatus;
use App\Enums\PlacementStatus;
use App\Enums\SchoolPlan;
use App\Models\Journal;
use App\Models\PklPeriod;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\Export\ClassLogbookArchive;
use App\Support\SpreadsheetHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class HubinReportController extends Controller
{
    public function show(Request $request): View|BinaryFileResponse
    {
        $school = $request->user()->school;
        abort_unless($school->plan() === SchoolPlan::Pro, 403);
        $filters = $request->validate([
            'period' => ['nullable', 'uuid'],
            'major' => ['nullable', 'string', 'max:100'],
            'class_name' => ['required_if:download,zip', 'nullable', 'string', 'max:64'],
            'teacher' => ['nullable', 'uuid'],
            'download' => ['nullable', 'in:xlsx,zip'],
        ]);
        $periods = PklPeriod::query()->orderByDesc('is_active')->orderByDesc('start_date')->get();
        $period = isset($filters['period']) ? $periods->firstWhere('id', $filters['period']) : $periods->first();
        abort_if(isset($filters['period']) && ! $period, 404);

        $today = now($school->timezone)->toDateString();
        $present = [AttendanceStatus::Present->value, AttendanceStatus::Late->value];
        $placements = Placement::query()
            ->where('pkl_period_id', $period?->id)
            ->whereIn('status', [PlacementStatus::Active, PlacementStatus::Completed])
            ->with(['student.user', 'teacher.user', 'assessment'])
            ->when(isset($filters['major']), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('major', $filters['major'])))
            ->when(isset($filters['class_name']), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('class_name', $filters['class_name'])))
            ->when(isset($filters['teacher']), fn ($q) => $q->where('teacher_id', $filters['teacher']))
            ->withCount([
                'attendances as present_days' => fn ($q) => $q->whereIn('status', $present),
                'journals as approved_journals' => fn ($q) => $q->where('status', JournalStatus::Approved),
                'journals as revisions' => fn ($q) => $q->where('status', JournalStatus::RevisionNeeded),
                'attendances as missing_journals' => fn ($q) => $q->whereIn('status', $present)
                    ->where('attendance_date', '<', $today)
                    ->whereNotExists(Journal::query()->selectRaw('1')
                        ->whereColumn('journals.placement_id', 'attendances.placement_id')
                        ->whereColumn('journals.journal_date', 'attendances.attendance_date')
                        ->where('journals.status', '!=', JournalStatus::Draft)->toBase()),
            ])->get()->sortBy(fn ($p) => [$p->student->class_name, $p->student->user->name]);

        $download = $filters['download'] ?? null;
        if ($download !== null) {
            abort_if($placements->isEmpty(), 404);
            if ($download === 'zip') {
                return (new ClassLogbookArchive)->download($placements, $period, $filters['class_name']);
            }

            return $this->downloadRecap($placements, $period);
        }

        $followUp = $placements->filter(fn ($p) => $p->missing_journals || $p->revisions ||
            ((! $p->assessment?->finalized_at) && ($p->status === PlacementStatus::Completed || $period->end_date->toDateString() < $today)));

        return view('admin.hubin', [
            'periods' => $periods, 'period' => $period, 'placements' => $placements, 'followUp' => $followUp,
            'majors' => Student::query()->distinct()->orderBy('major')->pluck('major'),
            'classes' => Student::query()->distinct()->orderBy('class_name')->pluck('class_name'),
            'teachers' => Teacher::with('user')->get()->sortBy('user.name'),
        ]);
    }

    private function downloadRecap(Collection $placements, PklPeriod $period): BinaryFileResponse
    {
        $rows = [['Periode', 'Siswa', 'NISN', 'Jurusan', 'Kelas', 'Pembimbing', 'Hari hadir', 'Jurnal disetujui',
            'Hari hadir tanpa jurnal terkirim', 'Jurnal perlu revisi', 'Status nilai', 'Nilai akhir']];
        foreach ($placements as $placement) {
            $assessment = $placement->assessment;
            $rows[] = [$period->name, $placement->student->user->name, $placement->student->nisn,
                $placement->student->major, $placement->student->class_name, $placement->teacher->user->name,
                $placement->present_days, $placement->approved_journals, $placement->missing_journals, $placement->revisions,
                $assessment?->finalized_at ? 'Final' : 'Belum final', $assessment?->finalized_at ? $assessment->final_score : ''];
        }
        $path = tempnam(sys_get_temp_dir(), 'jurnalin-rekap-');
        try {
            SpreadsheetHelper::writeXlsx($path, $rows);

            return response()->download($path, 'rekap-hubin-'.Str::slug($period->name).'.xlsx')->deleteFileAfterSend(true);
        } catch (Throwable $error) {
            unlink($path);
            throw $error;
        }
    }
}
