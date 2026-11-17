<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadinessModule extends Model
{
    use BelongsToSchool, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quiz' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ReadinessResult::class, 'readiness_module_id');
    }
}
