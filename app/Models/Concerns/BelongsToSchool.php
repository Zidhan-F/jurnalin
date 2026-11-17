<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school_tenant', function (Builder $builder): void {
            if (TenantContext::isSuperAdmin()) {
                return;
            }

            $schoolId = TenantContext::currentSchoolId();
            if ($schoolId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->qualifyColumn('school_id'), $schoolId);
        });

        static::creating(function (Model $model): void {
            if (empty($model->school_id) && TenantContext::currentSchoolId() !== null) {
                $model->setAttribute('school_id', TenantContext::currentSchoolId());
            }
        });
    }
}
