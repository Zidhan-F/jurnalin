<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/** Menu bawah siswa (HP) dan sidebar siswa (desktop). Siswa masa tambahan hanya melihat Jurnal. */
final class StudentNavigation
{
    /** @return list<array{route: string, label: string, icon: string}> */
    public static function items(Request $request): array
    {
        $items = [
            ['route' => 'student.home', 'label' => 'Beranda', 'icon' => 'school'],
            ['route' => 'student.attendance', 'label' => 'Presensi', 'icon' => 'calendar'],
            ['route' => 'student.journals', 'label' => 'Jurnal', 'icon' => 'journal'],
            ['route' => 'student.leaves', 'label' => 'Izin', 'icon' => 'alert'],
            ['route' => 'student.report', 'label' => 'Laporan', 'icon' => 'award'],
        ];

        if ($request->attributes->get('grace_placements') && ! $request->attributes->get('full_placements')) {
            return array_values(array_filter($items, fn (array $i) => $i['route'] === 'student.journals'));
        }

        return $items;
    }

    /** Tautan Bantuan di sidebar desktop; tidak ada untuk siswa masa tambahan karena halamannya tertutup. */
    public static function help(Request $request): ?array
    {
        if ($request->attributes->get('grace_placements') && ! $request->attributes->get('full_placements')) {
            return null;
        }

        return ['route' => 'student.tickets', 'label' => 'Bantuan'];
    }
}
