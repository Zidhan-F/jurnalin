<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Enums\JournalStatus;
use App\Enums\PlacementStatus;
use App\Models\Attendance;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\PklPeriod;
use App\Models\School;
use App\Models\Student;
use Carbon\CarbonImmutable;

/**
 * Angka dashboard koordinator PKL untuk satu periode terpilih. Semua kueri berjalan di tenant scope.
 * Angka "hari ini" hanya dipakai bila hari ini berada di dalam rentang periode; selain itu memakai
 * komposisi seluruh periode.
 */
final class AdminDashboard
{
    /** Jurnal dianggap tertunda lama bila tanggalnya lebih tua dari sekian hari dan belum diperiksa guru. */
    public const STALE_JOURNAL_DAYS = 3;

    /** Penilaian dianggap siap diisi bila periode tinggal sekian hari atau kurang. */
    public const ASSESSMENT_WINDOW_DAYS = 14;

    private const PRESENT = [AttendanceStatus::Present, AttendanceStatus::Late];

    private const LEAVE = [AttendanceStatus::Sick, AttendanceStatus::Permission];

    public static function build(School $school, ?PklPeriod $period, CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $unplaced = Student::query()->whereDoesntHave('placements', fn ($q) => $q->where('status', '!=', PlacementStatus::Cancelled))->count();

        if ($period === null) {
            return ['period' => null, 'previousPeriod' => null, 'isRunning' => false, 'kpi' => null, 'journalTrend' => null, 'status' => null,
                'priorities' => [self::priority('unplaced', 'Lengkapi data penempatan', 'Siswa yang belum punya tempat PKL.', $unplaced, route('admin.placements'))]];
        }

        $placements = Placement::query()->where('pkl_period_id', $period->id)->where('status', '!=', PlacementStatus::Cancelled);
        $placementIds = (clone $placements)->pluck('id');
        $activeIds = (clone $placements)->where('status', PlacementStatus::Active)->pluck('id');
        $isRunning = $today->betweenIncluded($period->start_date->copy()->startOfDay(), $period->end_date->copy()->startOfDay());

        $previous = PklPeriod::query()->whereDate('end_date', '<', $period->start_date->toDateString())->orderByDesc('end_date')->first();
        $prevPlacements = $previous ? Placement::query()->where('pkl_period_id', $previous->id)->where('status', '!=', PlacementStatus::Cancelled) : null;

        $participants = (clone $placements)->distinct()->count('student_id');
        $companyIds = (clone $placements)->distinct()->pluck('company_id');
        $prevCompanyIds = $prevPlacements ? (clone $prevPlacements)->distinct()->pluck('company_id') : null;

        $pendingQuery = Journal::query()->whereIn('placement_id', $placementIds)->where('status', JournalStatus::Submitted);
        $pending = (clone $pendingQuery)->count();
        $stale = (clone $pendingQuery)->whereDate('journal_date', '<', $today->subDays(self::STALE_JOURNAL_DAYS)->toDateString())->count();

        $attendance = Attendance::query()->whereIn('placement_id', $isRunning ? $activeIds : $placementIds)
            ->when($isRunning, fn ($q) => $q->whereDate('attendance_date', $today->toDateString()))
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $sum = fn (array $statuses) => (int) collect($statuses)->sum(fn (AttendanceStatus $s) => $attendance[$s->value] ?? 0);
        $present = $sum(self::PRESENT);
        $leave = $sum(self::LEAVE);
        if ($isRunning) {
            $base = $activeIds->count();
            $missing = max(0, $base - $present - $leave);
        } else {
            $missing = $sum([AttendanceStatus::Absent]);
            $base = $present + $leave + $missing;
        }

        return [
            'period' => $period,
            'previousPeriod' => $previous,
            'isRunning' => $isRunning,
            'kpi' => [
                'participants' => $participants,
                'participantsDelta' => $prevPlacements ? $participants - (clone $prevPlacements)->distinct()->count('student_id') : null,
                'companies' => $companyIds->count(),
                'newCompanies' => $prevCompanyIds ? $companyIds->diff($prevCompanyIds)->count() : null,
                'pendingJournals' => $pending,
                'present' => $present,
                'presentBase' => $base,
                'attendanceRate' => $base > 0 ? (int) round($present / $base * 100) : null,
            ],
            'priorities' => [
                self::priority('journals', 'Periksa jurnal peserta', "Ada {$pending} jurnal menunggu review guru, {$stale} di antaranya lebih dari ".self::STALE_JOURNAL_DAYS.' hari.',
                    $pending, route('admin.pending-journals', $stale ? ['filter' => 'lama'] : [])),
                self::priority('absent', 'Konfirmasi presensi tidak hadir', $isRunning ? 'Peserta aktif yang belum absen atau mengajukan izin hari ini.' : 'Periode ini tidak sedang berjalan.',
                    $isRunning ? $missing : 0, route('monitor.alerts')),
                self::priority('unplaced', 'Lengkapi data penempatan', 'Siswa yang belum punya tempat PKL.', $unplaced, route('admin.placements')),
                self::priority('assessment', 'Input atau verifikasi penilaian', 'Peserta yang sudah dapat dinilai tetapi nilainya belum final.',
                    self::assessmentReady($period, $today), route('admin.periods.show', $period)),
            ],
            'journalTrend' => self::journalTrend($placementIds, $isRunning ? $today : $period->end_date->toImmutable()->startOfDay()),
            'status' => [
                'labels' => ['Hadir', 'Izin/Sakit', $isRunning ? 'Belum/Tidak hadir' : 'Tidak hadir'],
                'values' => [$present, $leave, $missing],
                'scope' => $isRunning ? 'today' : 'period',
            ],
        ];
    }

    private static function priority(string $key, string $label, string $detail, int $count, string $href): array
    {
        return compact('key', 'label', 'detail', 'count', 'href');
    }

    /** Penempatan aktif/selesai tanpa nilai final yang periodenya hampir habis atau siswanya sudah memenuhi target hadir. */
    private static function assessmentReady(PklPeriod $period, CarbonImmutable $today): int
    {
        $nearEnd = $today->greaterThanOrEqualTo($period->end_date->toImmutable()->startOfDay()->subDays(self::ASSESSMENT_WINDOW_DAYS));

        return Placement::query()->where('pkl_period_id', $period->id)
            ->whereIn('status', [PlacementStatus::Active, PlacementStatus::Completed])
            ->whereDoesntHave('assessment', fn ($q) => $q->whereNotNull('finalized_at'))
            ->withCount(['attendances as attended' => fn ($q) => $q->whereIn('status', self::PRESENT)])
            ->get()
            ->filter(fn (Placement $p) => $nearEnd || $p->attended >= $period->min_required_days)
            ->count();
    }

    /** Jumlah jurnal terkirim per tanggal untuk 7 hari yang berakhir di $end, plus perbandingan dengan 7 hari sebelumnya. */
    private static function journalTrend($placementIds, CarbonImmutable $end): array
    {
        $start = $end->subDays(13);
        $counts = Journal::query()->whereIn('placement_id', $placementIds)->where('status', '!=', JournalStatus::Draft)
            ->whereBetween('journal_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('journal_date::date as d, count(*) as c')->groupBy('d')->pluck('c', 'd');
        $days = collect(range(6, 0))->map(fn (int $i) => $end->subDays($i));
        $values = $days->map(fn (CarbonImmutable $d) => (int) ($counts[$d->toDateString()] ?? 0))->all();
        $previous = (int) collect(range(13, 7))->sum(fn (int $i) => $counts[$end->subDays($i)->toDateString()] ?? 0);
        $current = array_sum($values);

        return [
            'labels' => $days->map(fn (CarbonImmutable $d) => $d->locale('id')->translatedFormat('D'))->all(),
            'values' => $values,
            'total' => Journal::query()->whereIn('placement_id', $placementIds)->where('status', '!=', JournalStatus::Draft)->count(),
            'changePercent' => $previous > 0 ? (int) round(($current - $previous) / $previous * 100) : null,
        ];
    }
}
