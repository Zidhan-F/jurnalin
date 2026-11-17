<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorEvaluation extends Model
{
    use BelongsToSchool, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rubric' => 'array',
            'scores' => 'array',
            'statement_accepted_at' => 'datetime',
            'otp_verified_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(PartnerContact::class, 'partner_contact_id');
    }

    public function internship(): BelongsTo
    {
        return $this->belongsTo(TeacherInternship::class, 'teacher_internship_id');
    }
}
