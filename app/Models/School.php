<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SchoolStatus;
use App\Enums\SchoolPlan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasUuids;

    protected $fillable = [
        'npsn',
        'name',
        'address',
        'city',
        'province',
        'timezone',
        'logo_path',
        'letterhead_config',
        'default_geofence_radius_m',
        'late_tolerance_minutes',
        'require_attendance_photo',
        'require_industry_form_photo',
        'subscription_tier',
        'max_students_quota',
        'scheduled_plan',
        'scheduled_plan_on',
        'status',
        'verified_at',
        'terms_accepted_at',
        'mentor_signoff_frequency',
        'deletion_requested_at',
        'dpa_accepted_at',
        'dpa_version',
        'privacy_notice_version',
        'max_journal_photos',
        'journal_approver',
        'peta_dudi_opt_in',
        'selfie_retention_days',
        'wa_notifications',
    ];

    protected function casts(): array
    {
        return [
            'letterhead_config' => 'array',
            'default_geofence_radius_m' => 'integer',
            'late_tolerance_minutes' => 'integer',
            'require_attendance_photo' => 'boolean',
            'require_industry_form_photo' => 'boolean',
            'max_students_quota' => 'integer',
            'status' => SchoolStatus::class,
            'verified_at' => 'datetime',
            'scheduled_plan_on' => 'date',
            'terms_accepted_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'dpa_accepted_at' => 'datetime',
            'max_journal_photos' => 'integer',
            'peta_dudi_opt_in' => 'boolean',
            'selfie_retention_days' => 'integer',
            'wa_notifications' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function pklPeriods(): HasMany
    {
        return $this->hasMany(PklPeriod::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(TenantFeature::class);
    }

    /** Modul add-on (S14) aktif bila tercatat di tenant_features; modul inti selalu aktif. */
    public function hasFeature(string $feature): bool
    {
        return \App\Support\Features::enabledFor($this, $feature);
    }

    public function isActive(): bool
    {
        return $this->status === SchoolStatus::Active;
    }

    public function plan(): SchoolPlan
    {
        // Turun paket terjadwal berlaku sendiri pada tanggalnya, tanpa perlu tugas terjadwal.
        if ($this->scheduled_plan && $this->scheduled_plan_on && $this->scheduled_plan_on->toDateString() <= now($this->timezone)->toDateString()) {
            return SchoolPlan::from($this->scheduled_plan);
        }

        return SchoolPlan::from($this->subscription_tier);
    }

    public function studentLimit(): int
    {
        return $this->plan() === SchoolPlan::Free
            ? min($this->max_students_quota, SchoolPlan::FREE_STUDENT_LIMIT)
            : $this->max_students_quota;
    }
}
