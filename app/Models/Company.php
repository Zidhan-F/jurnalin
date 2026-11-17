<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\ValueObjects\Coordinates;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use BelongsToSchool, HasUuids;

    protected $fillable = [
        'school_id',
        'name',
        'industry_type',
        'address',
        'city',
        'contact_person_name',
        'contact_person_phone',
        'latitude',
        'longitude',
        'geofence_radius_m',
        'npwp',
        'google_place_id',
        'is_active',
        'partnership_status',
        'relation_manager_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'geofence_radius_m' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public const PARTNERSHIP_LABELS = ['prospect' => 'Prospek', 'active' => 'Aktif', 'paused' => 'Jeda', 'archived' => 'Arsip'];

    public function contacts(): HasMany
    {
        return $this->hasMany(PartnerContact::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(PartnerAgreement::class)->orderByDesc('expires_on');
    }

    public function quotas(): HasMany
    {
        return $this->hasMany(PartnerQuota::class)->latest();
    }

    public function partnershipLabel(): string
    {
        return self::PARTNERSHIP_LABELS[$this->partnership_status] ?? (string) $this->partnership_status;
    }

    public function coordinates(): Coordinates
    {
        return new Coordinates((float) $this->latitude, (float) $this->longitude);
    }

    public function effectiveRadiusMeters(): int
    {
        return $this->geofence_radius_m ?? $this->school->default_geofence_radius_m ?? 200;
    }
}
