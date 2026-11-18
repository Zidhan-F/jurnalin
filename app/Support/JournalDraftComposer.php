<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Penyusun draf jurnal tanpa AI (P26 cadangan). Hanya merapikan poin siswa menjadi uraian berurutan,
 * memisahkan kendala, dan menyarankan TP dari kata kunci. Tidak pernah menambah kegiatan atau hasil.
 */
final class JournalDraftComposer
{
    private const OBSTACLE = '/\b(macet|rusak|error|eror|gagal|kendala|terlambat|telat|hilang|habis|kurang|komplain|complain|lupa|tidak bisa|gak bisa|nggak bisa|susah|sulit|putus|mati|bocor|lambat|salah)\b/iu';

    private const TYPOS = [
        'romm' => 'room', 'roomm' => 'room', 'clening' => 'cleaning', 'cleanning' => 'cleaning', 'chek' => 'check', 'cek in' => 'check-in',
        'cek out' => 'check-out', 'chekin' => 'check-in', 'checkin' => 'check-in', 'checkout' => 'check-out', 'make up room' => 'make-up room',
        'bed meking' => 'bed making', 'gest' => 'guest', 'resevasi' => 'reservasi', 'reservsi' => 'reservasi', 'tamuu' => 'tamu',
        'ngecek' => 'mengecek', 'nyapu' => 'menyapu', 'ngepel' => 'mengepel', 'bantuin' => 'membantu', 'ngelayani' => 'melayani',
    ];

    /** Kata kunci TP bawaan (TP1-TP5); kode yang tidak ada di katalog sekolah diabaikan. */
    private const OBJECTIVE_HINTS = [
        'TP1' => ['briefing', 'tamu', 'guest', 'pelanggan', 'customer', 'melayani', 'layani', 'telepon', 'koordinasi', 'komunikasi', 'sapa', 'greeting'],
        'TP2' => ['k3', 'cleaning', 'clean', 'apd', 'sanitasi', 'higiene', 'hygiene', 'safety', 'chemical', 'kimia', 'lantai basah', 'sarung tangan', 'masker', 'bersih'],
        'TP5' => ['jual', 'penjualan', 'sales', 'upselling', 'upsell', 'harga', 'promo', 'stok', 'biaya', 'omzet'],
    ];

    private const STEPS = ['Pertama,', 'Setelah itu,', 'Kemudian,', 'Selanjutnya,', 'Lalu,'];

    /**
     * @param  list<string>  $lines
     * @return array{draft:string, obstacles:string, title:string, objective_codes:list<string>}
     */
    public static function compose(array $lines, Collection $objectives, ?string $workUnit = null): array
    {
        $clean = array_values(array_filter(array_map(fn ($l) => self::tidy($l), $lines)));
        $activities = array_values(array_filter($clean, fn ($l) => ! preg_match(self::OBSTACLE, $l)));
        $obstacles = array_values(array_filter($clean, fn ($l) => (bool) preg_match(self::OBSTACLE, $l)));

        $sentences = [];
        $where = filled($workUnit) ? ' di '.trim((string) $workUnit) : '';
        $count = count($activities);
        if ($count === 1) {
            $sentences[] = 'Hari ini'.$where.' saya mengerjakan '.self::lower($activities[0]).'.';
        } elseif ($count > 1) {
            $sentences[] = 'Hari ini'.$where." saya mengerjakan {$count} kegiatan.";
            foreach ($activities as $i => $a) {
                $lead = $i === $count - 1 ? 'Terakhir,' : self::STEPS[min($i, count(self::STEPS) - 1)];
                $sentences[] = $lead.' '.self::lower($a).'.';
            }
        }
        if ($obstacles !== []) {
            $sentences[] = 'Kendala yang saya hadapi: '.implode('; ', array_map(self::lower(...), $obstacles)).'.';
        }

        $haystack = mb_strtolower(implode(' ', $clean));
        $codes = $count > 0 ? ['TP3'] : [];
        foreach (self::OBJECTIVE_HINTS as $code => $words) {
            foreach ($words as $w) {
                if (preg_match('/(?<![a-z])'.preg_quote($w, '/').'(?![a-z])/u', $haystack)) {
                    $codes[] = $code;
                    break;
                }
            }
        }
        $known = $objectives->pluck('code')->all();

        return [
            'draft' => implode(' ', $sentences),
            'obstacles' => $obstacles === [] ? '' : implode('; ', $obstacles),
            'title' => $activities[0] ?? '',
            'objective_codes' => array_values(array_intersect(array_unique($codes), $known)),
        ];
    }

    /** Huruf kecil di awal kecuali singkatan (IT, AC, FO). */
    private static function lower(string $s): string
    {
        return preg_match('/^\p{Lu}{2}/u', $s) ? $s : mb_strtolower(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }

    private static function tidy(string $line): string
    {
        $line = trim(preg_replace('/\s+/u', ' ', ltrim($line, "-*•·0123456789.) \t")) ?? '', " .\t");
        if ($line === '') {
            return '';
        }
        foreach (self::TYPOS as $wrong => $right) {
            $line = preg_replace('/(?<![a-z])'.preg_quote($wrong, '/').'(?![a-z])/iu', $right, $line) ?? $line;
        }

        return mb_strtoupper(mb_substr($line, 0, 1)).mb_substr($line, 1);
    }
}
