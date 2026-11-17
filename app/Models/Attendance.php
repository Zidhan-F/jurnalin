<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Attendance extends Model
{
    use BelongsToSchool, HasUuids, \App\Models\Concerns\RespectsGroupAccess;

    protected $fillable = [
        'school_id',
        'placement_id',
        'student_id',
        'attendance_date',
        'status',
        'check_in_at',
        'check_in_lat',
        'check_in_lng',
        'check_in_distance_m',
        'check_in_gps_accuracy_m',
        'check_in_photo_path',
        'is_within_radius',
        'check_out_at',
        'check_out_lat',
        'check_out_lng',
        'check_out_distance_m',
        'check_out_photo_path',
        'exception_reason',
        'approved_by_teacher_id',
        'flags',
        'review_status',
        'review_note',
        'reviewed_at',
        'device_hash',
        'leave_request_id',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'status' => AttendanceStatus::class,
            'check_in_at' => 'datetime',
            'check_in_lat' => 'float',
            'check_in_lng' => 'float',
            'check_in_distance_m' => 'integer',
            'check_in_gps_accuracy_m' => 'integer',
            'is_within_radius' => 'boolean',
            'check_out_at' => 'datetime',
            'check_out_lat' => 'float',
            'check_out_lng' => 'float',
            'check_out_distance_m' => 'integer',
            'flags' => 'array',
            'reviewed_at' => 'datetime',
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

    public function approvedByTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'approved_by_teacher_id');
    }

    public const FLAG_LABELS = [
        'outside_radius' => 'Di luar radius',
        'low_accuracy' => 'Akurasi GPS rendah',
        'distance_jump' => 'Lompatan jarak tidak wajar',
        'device_changed' => 'Perangkat berganti',
    ];

    public function isFlagged(): bool
    {
        return ! empty($this->flags);
    }

    /** @return list<string> */
    public function flagLabels(): array
    {
        return array_map(fn ($f) => self::FLAG_LABELS[$f] ?? $f, $this->flags ?? []);
    }

    public function journal(): HasOne
    {
        return $this->hasOne(Journal::class);
    }
}
