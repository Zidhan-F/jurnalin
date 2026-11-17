<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use BelongsToSchool, HasUuids;

    protected $fillable = [
        'school_id',
        'user_id',
        'nisn',
        'nis',
        'class_name',
        'major',
        'gender',
        'parent_phone',
        'birth_date',
        'guardian_name',
        'graduated_at',
        'alumni_contact_ok',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'graduated_at' => 'date', 'alumni_contact_ok' => 'boolean'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(GuardianConsent::class)->latest();
    }

    /** Peserta di bawah 18 tahun (atau tanggal lahir belum diisi) diperlakukan sebagai anak (S13). */
    public function isMinor(): bool
    {
        return $this->birth_date === null || $this->birth_date->age < 18;
    }

    public function activeConsent(): ?GuardianConsent
    {
        return $this->consents()->whereNotNull('given_at')->whereNull('withdrawn_at')->first();
    }

    /** Lokasi dan swafoto hanya boleh diambil bila peserta dewasa atau persetujuan wali tercatat. */
    public function trackingAllowed(): bool
    {
        return ! $this->isMinor() || $this->activeConsent() !== null;
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }
}
