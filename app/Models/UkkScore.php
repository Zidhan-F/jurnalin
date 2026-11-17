<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UkkScore extends Model
{
    use BelongsToSchool, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(UkkParticipant::class, 'ukk_participant_id');
    }

    public function examinerContact(): BelongsTo
    {
        return $this->belongsTo(PartnerContact::class, 'examiner_contact_id');
    }

    public function examinerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examiner_user_id');
    }
}
