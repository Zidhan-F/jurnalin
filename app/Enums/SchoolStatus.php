<?php

declare(strict_types=1);

namespace App\Enums;

enum SchoolStatus: string
{
    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PendingVerification => 'Menunggu Verifikasi NPSN',
            self::Active => 'Aktif',
            self::Suspended => 'Ditangguhkan',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PendingVerification => in_array($next, [self::Active, self::Suspended], true),
            self::Active => $next === self::Suspended,
            self::Suspended => $next === self::Active,
        };
    }
}
