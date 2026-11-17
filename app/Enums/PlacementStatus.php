<?php

declare(strict_types=1);

namespace App\Enums;

enum PlacementStatus: string
{
    case Scheduled = 'scheduled';
    case Offered = 'offered';
    case Confirmed = 'confirmed';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Rencana',
            self::Offered => 'Ditawarkan',
            self::Confirmed => 'Dikonfirmasi',
            self::Active => 'Aktif PKL',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Scheduled => in_array($next, [self::Offered, self::Confirmed, self::Active, self::Cancelled], true),
            self::Offered => in_array($next, [self::Confirmed, self::Cancelled], true),
            self::Confirmed => in_array($next, [self::Active, self::Cancelled], true),
            self::Active => in_array($next, [self::Completed, self::Cancelled], true),
            self::Completed, self::Cancelled => false,
        };
    }
}
