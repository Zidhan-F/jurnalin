<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UkkSession extends Model
{
    use BelongsToSchool, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'instrument' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function venueCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'venue_company_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(UkkParticipant::class, 'ukk_session_id');
    }
}
