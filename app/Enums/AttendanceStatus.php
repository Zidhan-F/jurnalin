<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Sick = 'sick';
    case Permission = 'permission';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::Sick => 'Sakit',
            self::Permission => 'Izin',
            self::Absent => 'Alpa',
        };
    }

    public function countsTowardAttendanceScore(): bool
    {
        return in_array($this, [self::Present, self::Late], true);
    }
}
