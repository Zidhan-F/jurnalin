<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assessment extends Model
{
    use BelongsToSchool, HasUuids, \App\Models\Concerns\RespectsGroupAccess;

    protected $fillable = [
        'school_id',
        'placement_id',
        'student_id',
        'teacher_id',
        'school_scores',
        'industry_scores',
        'industry_form_photo_path',
        'attendance_score',
        'final_score',
        'predicate',
        'teacher_notes',
        'industry_notes',
        'certificate_number',
        'finalized_at',
        'version',
        'achievement_description',
        'objective_scores',
        'industry_source',
        'revision_reason',
        'snapshot',
        'snapshot_hash',
    ];

    protected function casts(): array
    {
        return [
            'school_scores' => 'array',
            'industry_scores' => 'array',
            'attendance_score' => 'float',
            'final_score' => 'float',
            'finalized_at' => 'datetime',
            'objective_scores' => 'array',
            'snapshot' => 'array',
            'version' => 'integer',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function versions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AssessmentVersion::class)->orderByDesc('version');
    }

    public function industrySourceLabel(): string
    {
        return $this->industry_source === 'mentor_access' ? 'Dinilai langsung oleh pembimbing lapangan lewat tautan aman' : 'Dokumen manual diunggah sekolah';
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
