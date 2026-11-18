<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PklPeriod;
use App\Support\PklProgress;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PklProgressTest extends TestCase
{
    private function period(): PklPeriod
    {
        // 1 Jan sampai 25 Mar 2027 = 84 hari = 12 minggu.
        return (new PklPeriod())->forceFill(['start_date' => '2027-01-01', 'end_date' => '2027-03-25']);
    }

    public function test_weeks_progress(): void
    {
        $this->assertSame(['current' => 4, 'total' => 12, 'percent' => 33], PklProgress::weeks($this->period(), CarbonImmutable::parse('2027-01-25')));
        $this->assertSame(['current' => 1, 'total' => 12, 'percent' => 8], PklProgress::weeks($this->period(), CarbonImmutable::parse('2027-01-01')));
    }

    public function test_weeks_clamped_outside_period(): void
    {
        $this->assertSame(['current' => 0, 'total' => 12, 'percent' => 0], PklProgress::weeks($this->period(), CarbonImmutable::parse('2026-12-20')));
        $this->assertSame(['current' => 12, 'total' => 12, 'percent' => 100], PklProgress::weeks($this->period(), CarbonImmutable::parse('2027-05-01')));
    }

    public function test_single_day_period_has_one_week(): void
    {
        $p = (new PklPeriod())->forceFill(['start_date' => '2027-01-01', 'end_date' => '2027-01-01']);
        $this->assertSame(['current' => 1, 'total' => 1, 'percent' => 100], PklProgress::weeks($p, CarbonImmutable::parse('2027-01-01')));
    }
}
