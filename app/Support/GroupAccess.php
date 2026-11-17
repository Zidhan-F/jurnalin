<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SchoolPlan;
use App\Models\School;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class GroupAccess
{
    public static function roster(School $school): Builder
    {
        return DB::table('placements as p')
            ->where('p.school_id', $school->id)
            ->join('pkl_periods as period', 'period.id', '=', 'p.pkl_period_id')
            ->leftJoin('billing_group_members as m', function ($join) {
                $join->on('m.pkl_period_id', '=', 'p.pkl_period_id')->on('m.student_id', '=', 'p.student_id');
            })
            ->leftJoin('billing_groups as g', 'g.id', '=', 'm.billing_group_id')
            ->select('p.id', 'p.student_id', 'p.teacher_id', 'p.status', 'p.legacy_access', 'g.id as group_id',
                'g.stopped', 'g.starts_on', 'g.paid_until', 'g.journal_until', 'period.end_date');
    }

    public static function phase(object $row, School $school, string $today): string
    {
        if ($row->status === 'cancelled') {
            return 'off';
        }
        if ($row->group_id) {
            if ($row->stopped || ! $row->starts_on || $today < $row->starts_on) {
                return 'off';
            }
            $end = $row->paid_until;
            $last = $row->journal_until;
        } else {
            if ($school->require_group_payment || (! $row->legacy_access && $school->plan() !== SchoolPlan::Free)) {
                return 'off';
            }
            $end = $row->end_date;
            $last = CarbonImmutable::parse($end)->addDay()->addMonthNoOverflow()->subDay()->toDateString();
        }

        return $today <= $end ? 'full' : ($today <= $last ? 'journal' : 'off');
    }
}
