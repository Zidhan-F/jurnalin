<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case SchoolAdmin = 'school_admin';
    case Teacher = 'teacher';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Admin',
            self::SchoolAdmin => 'Admin Sekolah (Hubin)',
            self::Teacher => 'Guru Pembimbing',
            self::Student => 'Siswa PKL',
        };
    }

    public function homePath(): string
    {
        return match ($this) {
            self::SuperAdmin => '/super-admin',
            self::SchoolAdmin => '/admin',
            self::Teacher => '/teacher',
            self::Student => '/student',
        };
    }
}
