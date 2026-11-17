<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Journal;
use App\Models\Placement;
use Illuminate\Database\Eloquent\Builder;

trait RespectsGroupAccess
{
    public static function bootRespectsGroupAccess(): void
    {
        static::addGlobalScope('group_access', function (Builder $query): void {
            $request = request();
            if (! $request->attributes->has('full_placements')) {
                return;
            }
            $journalRoute = $request->routeIs('student.journals*', 'teacher.index', 'teacher.journals.*', 'media.show');
            $includeGrace = $journalRoute && in_array(static::class, [Placement::class, Journal::class], true);
            $ids = $request->attributes->get($includeGrace ? 'journal_placements' : 'full_placements');
            $query->whereIn($query->qualifyColumn(static::class === Placement::class ? 'id' : 'placement_id'), $ids);
        });
    }
}
