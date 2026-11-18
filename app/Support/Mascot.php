<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Karakter kartun (Avataaars via DiceBear, badan maskot digambar khusus) dan ikon Fluent Emoji (MIT)
 * di public/images/illustrations, serta inisial avatar.
 * Maskot siswa mengikuti students.gender: L laki-laki, P perempuan, kosong netral.
 */
final class Mascot
{
    public const ROLES = ['student', 'teacher', 'admin', 'mentor', 'intern_teacher', 'examiner', 'alumni', 'superadmin'];

    private const BASE = '/images/illustrations/';

    public static function forStudent(?string $gender): string
    {
        return self::BASE.match (strtoupper(trim((string) $gender))) {
            'L' => 'student-male.svg',
            'P' => 'student-female.svg',
            default => 'student-neutral.svg',
        };
    }

    /** Maskot siswa seluruh badan (seragam, ransel, buku jurnal, melambai) untuk sapaan beranda. */
    public static function fullBody(?string $gender): string
    {
        return self::BASE.match (strtoupper(trim((string) $gender))) {
            'L' => 'mascot-male.svg',
            'P' => 'mascot-female.svg',
            default => 'mascot-neutral.svg',
        };
    }

    public static function teacher(): string
    {
        return self::BASE.'teacher.svg';
    }

    public static function role(string $key): string
    {
        return self::BASE.match ($key) {
            'student' => 'student-neutral.svg',
            'teacher', 'admin', 'mentor', 'intern_teacher', 'examiner', 'alumni', 'superadmin' => $key.'.svg',
            default => throw new \InvalidArgumentException("Peran ilustrasi tidak dikenal: {$key}"),
        };
    }

    /** Maksimal dua huruf kapital dari nama, mengabaikan gelar setelah koma. */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim(explode(',', $name)[0])) ?: [];
        $letters = array_map(fn (string $w) => mb_strtoupper(mb_substr($w, 0, 1)), array_filter($words));

        return implode('', array_slice($letters, 0, 2));
    }
}
