<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PklPeriod;
use Carbon\CarbonImmutable;

/** Progres periode PKL dalam minggu, untuk kartu "Progres PKL" siswa (Minggu ke-X dari Y). */
final class PklProgress
{
    /** @return array{current: int, total: int, percent: int} */
    public static function weeks(PklPeriod $period, CarbonImmutable $today): array
    {
        $start = CarbonImmutable::parse($period->start_date->toDateString());
        $end = CarbonImmutable::parse($period->end_date->toDateString());
        $day = CarbonImmutable::parse($today->toDateString());

        $total = max(1, (int) ceil(((int) $start->diffInDays($end) + 1) / 7));
        $current = match (true) {
            $day->lt($start) => 0,
            $day->gt($end) => $total,
            default => min($total, intdiv((int) $start->diffInDays($day), 7) + 1),
        };

        return ['current' => $current, 'total' => $total, 'percent' => (int) round($current / $total * 100)];
    }
}
