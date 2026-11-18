<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Grup penempatan: sekumpulan siswa di satu tempat PKL dengan satu guru pembimbing. */
class PlacementGroup extends Model
{
    use BelongsToSchool, HasUuids;

    protected $fillable = ['school_id', 'pkl_period_id', 'name', 'company_id', 'teacher_id', 'department_divisi', 'work_start_time', 'work_end_time'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PklPeriod::class, 'pkl_period_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }
}
