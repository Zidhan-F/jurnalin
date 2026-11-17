<?php

declare(strict_types=1);

namespace App\Enums;

enum SchoolPlan: string
{
    case Free = 'free_pilot';
    case Regular = 'per_student';
    case Pro = 'enterprise';

    public const FREE_STUDENT_LIMIT = 35;

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Regular => 'Sekolah Reguler',
            self::Pro => 'Sekolah Pro',
        };
    }

    public function price(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Regular => 3000,
            self::Pro => 5000,
        };
    }

    public function formattedPrice(): string
    {
        return 'Rp'.number_format($this->price(), 0, ',', '.');
    }
}
