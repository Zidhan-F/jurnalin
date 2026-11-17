<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Journal;

/**
 * QuickJournal (P06) dan template jurusan (P40). Template hanya contoh: siswa wajib menyesuaikan
 * dan mengonfirmasi bahwa kegiatan benar-benar dilakukan. Paket dipilih dari program keahlian siswa.
 */
final class JournalTemplates
{
    private const MATCHERS = [
        'perhotelan-fo' => ['perhotelan', 'hotel', 'akomodasi', 'pariwisata'],
        'perhotelan-hk' => ['perhotelan', 'hotel', 'akomodasi', 'pariwisata'],
        'perhotelan-fb' => ['perhotelan', 'hotel', 'kuliner', 'boga'],
        'kuliner' => ['kuliner', 'boga', 'tata boga', 'pastry'],
        'tkj-rpl' => ['komputer', 'jaringan', 'perangkat lunak', 'informatika', 'rpl', 'tkj', 'sija', 'gim', 'aplikasi'],
        'otomotif' => ['otomotif', 'kendaraan', 'sepeda motor', 'alat berat'],
        'akuntansi' => ['akuntansi', 'perkantoran', 'keuangan', 'manajemen', 'administrasi', 'bisnis'],
    ];

    /** @return array<string, array{label:string, items:list<array{key:string,title:string,description:string,objective:string}>}> */
    public static function forMajor(?string $major): array
    {
        $all = config('pkl.journal_templates');
        $major = mb_strtolower((string) $major);
        $packs = ['umum' => $all['umum']];
        foreach (self::MATCHERS as $key => $words) {
            foreach ($words as $w) {
                if ($major !== '' && str_contains($major, $w)) {
                    $packs[$key] = $all[$key];
                    break;
                }
            }
        }

        return $packs;
    }

    /** @return list<array{key:string,title:string,description:string,objective:string}> */
    public static function flat(?string $major): array
    {
        return collect(self::forMajor($major))->flatMap(fn ($p) => $p['items'])->values()->all();
    }

    /** Aktivitas yang pernah ditulis siswa (unik per judul), untuk diisi ulang lalu disesuaikan. */
    public static function recent(string $studentId, int $limit = 5): array
    {
        return Journal::query()->where('student_id', $studentId)->latest('journal_date')->limit(30)
            ->get(['activity_title', 'activity_description', 'work_unit', 'tools_materials'])
            ->unique('activity_title')->take($limit)->values()
            ->map(fn ($j) => ['key' => 'recent', 'title' => $j->activity_title, 'description' => $j->activity_description, 'work_unit' => $j->work_unit, 'tools' => $j->tools_materials])
            ->all();
    }
}
