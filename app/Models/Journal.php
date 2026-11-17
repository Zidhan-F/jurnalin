<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Journal extends Model
{
    use BelongsToSchool, HasUuids, \App\Models\Concerns\RespectsGroupAccess;

    protected $fillable = [
        'school_id',
        'placement_id',
        'student_id',
        'attendance_id',
        'journal_date',
        'work_unit',
        'activity_title',
        'activity_description',
        'tools_materials',
        'learning_outcome',
        'photo_path',
        'duration_minutes',
        'status',
        'teacher_feedback',
        'validated_by_teacher_id',
        'validated_at',
        'mentor_note',
        'mentor_note_at',
        'obstacles',
        'template_key',
        'photo_paths',
        'confirmed_done',
        'version',
        'reopened_reason',
        'reopened_at',
    ];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
            'duration_minutes' => 'integer',
            'status' => JournalStatus::class,
            'validated_at' => 'datetime',
            'mentor_note_at' => 'datetime',
            'photo_paths' => 'array',
            'confirmed_done' => 'boolean',
            'version' => 'integer',
            'reopened_at' => 'datetime',
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

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /** Simpan potret isi jurnal sebagai versi (audit trail P11). */
    public function recordVersion(?string $reason = null): void
    {
        JournalVersion::query()->create([
            'school_id' => $this->school_id,
            'journal_id' => $this->id,
            'version' => (int) ($this->version ?? 1),
            'status' => $this->status->value,
            'snapshot' => $this->only(['journal_date', 'work_unit', 'activity_title', 'activity_description', 'tools_materials', 'learning_outcome', 'obstacles', 'duration_minutes', 'photo_path', 'photo_paths', 'teacher_feedback']),
            'actor_user_id' => auth()->id(),
            'reason' => $reason,
        ]);
    }

    /** @return list<string> semua foto (foto utama lama + tambahan). */
    public function photos(): array
    {
        return array_values(array_unique(array_filter([$this->photo_path, ...($this->photo_paths ?? [])])));
    }

    public function versions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(JournalVersion::class)->orderByDesc('version');
    }

    public function objectives(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(LearningObjective::class, 'journal_objectives')->withPivot(['status', 'school_id', 'verified_at']);
    }

    public function validatedByTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'validated_by_teacher_id');
    }
}
