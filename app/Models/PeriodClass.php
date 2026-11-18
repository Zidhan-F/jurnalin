<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Kelas yang ikut satu periode PKL beserta daftar siswanya pada periode itu. */
class PeriodClass extends Model
{
    use BelongsToSchool, HasUuids;

    protected $fillable = ['school_id', 'pkl_period_id', 'name', 'major'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PklPeriod::class, 'pkl_period_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'period_class_members');
    }
}
