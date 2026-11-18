<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\JournalStatus;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Jadwal pemeriksaan jurnal per guru pembimbing: setiap hari, setiap 7 hari, setiap 30 hari, atau di akhir PKL.
 * Jadwal TIDAK terikat hari kalender (bukan "setiap Senin" atau "tanggal 1"), karena banyak tempat PKL
 * seperti hotel dan pariwisata tetap bekerja Sabtu-Minggu dan libur di hari biasa. Interval dihitung bergulir
 * sejak guru terakhir memeriksa; kelompok antrean dihitung dari tanggal mulai PKL ("Minggu ke-3 PKL").
 */
final class ReviewSchedule
{
    public const FREQUENCIES = [
        'daily' => 'Setiap hari',
        'weekly' => 'Setiap 7 hari',
        'monthly' => 'Setiap 30 hari',
        'end' => 'Di akhir PKL',
    ];

    public const END_WINDOW_DAYS = 7;

    public static function frequency(?Teacher $teacher): string
    {
        $f = $teacher?->review_frequency;

        return array_key_exists((string) $f, self::FREQUENCIES) ? $f : 'weekly';
    }

    public static function label(string $frequency): string
    {
        return self::FREQUENCIES[$frequency] ?? $frequency;
    }

    public static function intervalDays(string $frequency): ?int
    {
        return ['daily' => 1, 'weekly' => 7, 'monthly' => 30][$frequency] ?? null;
    }

    /**
     * Kelompok antrean untuk satu tanggal jurnal. Mingguan/bulanan dihitung dari tanggal mulai PKL,
     * jadi "Minggu ke-1" bisa dimulai hari apa pun.
     *
     * @return array{key:string, label:string}
     */
    public static function bucket(CarbonInterface $date, string $frequency, ?CarbonInterface $periodStart = null): array
    {
        $d = CarbonImmutable::parse($date->toDateString());
        $interval = self::intervalDays($frequency);
        if ($frequency === 'end') {
            return ['key' => 'all', 'label' => 'Seluruh periode PKL'];
        }
        if ($interval === 1 || $periodStart === null) {
            return ['key' => $d->toDateString(), 'label' => $d->translatedFormat('l, d M Y')];
        }
        $start = CarbonImmutable::parse($periodStart->toDateString());
        $index = intdiv(max(0, (int) $start->diffInDays($d)), $interval);
        $from = $start->addDays($index * $interval);
        $until = $from->addDays($interval - 1);
        $unit = $frequency === 'monthly' ? 'Bulan' : 'Minggu';

        return ['key' => $start->toDateString().'#'.$index, 'label' => "{$unit} ke-".($index + 1).' PKL ('.$from->format('d M').' s.d. '.$until->translatedFormat('d M Y').')'];
    }

    /**
     * Status jadwal satu guru: kapan periksa berikutnya dan apakah sudah waktunya.
     * Patokan: pemeriksaan terakhir guru; bila belum pernah, jurnal tertua yang menunggu.
     *
     * @return array{frequency:string, next:CarbonImmutable, due:bool, pending:int}
     */
    public static function forTeacher(Teacher $teacher, CarbonInterface $today): array
    {
        $frequency = self::frequency($teacher);
        $t = CarbonImmutable::parse($today->toDateString());
        $placements = Placement::query()->with('pklPeriod')->where('teacher_id', $teacher->id)->where('status', 'active')->get();
        $pendingQuery = Journal::query()->whereIn('placement_id', $placements->pluck('id'))->where('status', JournalStatus::Submitted);
        $pending = (clone $pendingQuery)->count();
        $oldest = (clone $pendingQuery)->min('journal_date');

        if ($frequency === 'end') {
            $end = $placements->map(fn ($p) => $p->pklPeriod->end_date)->min();
            $next = $end ? CarbonImmutable::parse($end->toDateString())->subDays(self::END_WINDOW_DAYS)->max($t) : $t;
        } elseif ($frequency === 'daily') {
            $next = $t;
        } else {
            $anchor = $teacher->last_reviewed_at ?? ($oldest ? CarbonImmutable::parse($oldest) : null);
            $next = $anchor ? CarbonImmutable::parse($anchor->toDateString())->addDays(self::intervalDays($frequency))->max($t) : $t->addDays(self::intervalDays($frequency));
        }

        return ['frequency' => $frequency, 'next' => $next, 'due' => $pending > 0 && $next->lte($t), 'pending' => $pending];
    }

    /** Antrean dianggap terlambat bila jurnal lebih tua dari batas ini (null = baru terlambat setelah periode selesai). */
    public static function staleDays(string $frequency): ?int
    {
        return match ($frequency) {
            'daily' => 3,
            'weekly' => 10,
            'monthly' => 35,
            default => null,
        };
    }

    /** Kalimat untuk siswa: kapan guru akan memeriksa jurnalnya. */
    public static function studentNotice(Placement $placement, CarbonInterface $today): string
    {
        $teacher = $placement->teacher;
        if ($teacher === null || self::frequency($teacher) === 'daily') {
            return 'Guru pembimbing memeriksa jurnal setiap hari.';
        }
        $s = self::forTeacher($teacher, $today);
        $when = $s['next']->isSameDay($today) ? 'hari ini' : 'sekitar '.$s['next']->translatedFormat('l, d M Y');

        return 'Guru pembimbing memeriksa jurnal '.mb_strtolower(self::label($s['frequency'])).'. Pemeriksaan berikutnya '.$when.'. Tetap isi jurnal setiap hari kerja Anda, termasuk Sabtu atau Minggu bila tempat PKL buka.';
    }
}
