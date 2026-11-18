<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SchoolPlan;
use App\Models\School;
use App\Models\TenantFeature;

/**
 * Aktivasi modul per tenant (S14). Modul inti PKL selalu aktif; add-on dijual terpisah dan
 * hanya terbuka bila tercatat aktif di tenant_features. Menonaktifkan modul tidak menghapus data.
 */
final class Features
{
    public const ADDONS = [
        'hubin' => 'Hubin Partner Hub',
        'internship' => 'Magang Guru Industri',
        'ai' => 'Asisten AI & Laporan Akhir Otomatis',
        'ukk' => 'Uji Kompetensi Keahlian (UKK)',
        'tracer' => 'Tracer Study Internal',
        'whatsapp' => 'Ringkasan & pengingat WhatsApp otomatis',
    ];

    /** Fitur paket Pro (S06): terbuka otomatis untuk paket berbayar, bisa dibuka manual sebagai uji coba. */
    public const PRO = [
        'mentor_access' => 'Akses pembimbing industri (penilaian lewat tautan)',
        'passport' => 'Paspor Kompetensi',
    ];

    /** @var array<string, array<string, bool>> */
    private static array $cache = [];

    public static function label(string $feature): string
    {
        return self::ADDONS[$feature] ?? self::PRO[$feature] ?? $feature;
    }

    public static function enabledFor(School $school, string $feature): bool
    {
        $isAddon = array_key_exists($feature, self::ADDONS);
        $isPro = array_key_exists($feature, self::PRO);
        if (! $isAddon && ! $isPro) {
            return true;
        }

        $rows = self::$cache[$school->id] ??= TenantFeature::query()
            ->where('school_id', $school->id)
            ->pluck('enabled', 'feature')
            ->map(fn ($v) => (bool) $v)
            ->all();

        if (array_key_exists($feature, $rows)) {
            return $rows[$feature];
        }

        return $isPro && $school->plan() !== SchoolPlan::Free;
    }

    public static function set(School $school, string $feature, bool $enabled, string $source = 'addon'): void
    {
        TenantFeature::query()->updateOrCreate(
            ['school_id' => $school->id, 'feature' => $feature],
            ['enabled' => $enabled, 'source' => $source, 'activated_at' => $enabled ? now() : null],
        );
        unset(self::$cache[$school->id]);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
