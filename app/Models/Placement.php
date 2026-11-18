<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlacementStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Placement extends Model
{
    use BelongsToSchool, HasUuids, \App\Models\Concerns\RespectsGroupAccess;

    protected $fillable = [
        'school_id',
        'pkl_period_id',
        'student_id',
        'company_id',
        'teacher_id',
        'department_divisi',
        'industry_mentor_name',
        'industry_mentor_phone',
        'work_start_time',
        'work_end_time',
        'status',
        'mentor_link_hash',
        'mentor_link_token',
        'mentor_link_expires_at',
        'latitude',
        'longitude',
        'geofence_radius_m',
        'mentor_contact_id',
        'placement_group_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PlacementStatus::class,
            'mentor_link_token' => 'encrypted',
            'mentor_link_expires_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'geofence_radius_m' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function pklPeriod(): BelongsTo
    {
        return $this->belongsTo(PklPeriod::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }

    public function mentorSignoffs(): HasMany
    {
        return $this->hasMany(MentorSignoff::class)->orderByDesc('period_start');
    }

    public function mentorContact(): BelongsTo
    {
        return $this->belongsTo(PartnerContact::class, 'mentor_contact_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class)->latest();
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class)->where('kind', 'pkl')->latest('issued_at');
    }

    /** Titik presensi: koordinat penempatan bila diisi (P04), selain itu titik tempat PKL. */
    public function attendancePoint(): \App\ValueObjects\Coordinates
    {
        return $this->latitude !== null && $this->longitude !== null
            ? new \App\ValueObjects\Coordinates((float) $this->latitude, (float) $this->longitude)
            : $this->company->coordinates();
    }

    public function attendanceRadius(): int
    {
        return $this->geofence_radius_m ?? $this->company->effectiveRadiusMeters();
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(Assessment::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PlacementGroup::class, 'placement_group_id');
    }
}
