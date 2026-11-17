<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherInternship extends Model
{
    use BelongsToSchool, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(PartnerContact::class, 'partner_contact_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'reviewer_teacher_id');
    }

    public function journals(): HasMany
    {
        return $this->hasMany(InternshipJournal::class, 'teacher_internship_id');
    }

    public function transferPlans(): HasMany
    {
        return $this->hasMany(TransferPlan::class, 'teacher_internship_id');
    }

    public function disseminations(): HasMany
    {
        return $this->hasMany(DisseminationRecord::class, 'teacher_internship_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(MentorEvaluation::class, 'teacher_internship_id');
    }
}
