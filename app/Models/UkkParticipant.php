<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UkkParticipant extends Model
{
    use BelongsToSchool, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'final_score' => 'float',
            'snapshot' => 'array',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(UkkSession::class, 'ukk_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(UkkScore::class, 'ukk_participant_id');
    }
}
