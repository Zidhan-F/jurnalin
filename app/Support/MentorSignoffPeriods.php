<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Enums\JournalStatus;
use App\Models\Attendance;
use App\Models\Journal;
use App\Models\MentorSignoff;
use App\Models\Placement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Membagi jurnal satu penempatan ke rentang tanda tangan DUDI (hari / minggu Senin-Minggu / bulan).
 * Harus dipanggil di dalam konteks sekolah penempatan tersebut.
 */
final class MentorSignoffPeriods
{
    public const FREQUENCY_LABELS = ['daily' => 'per hari', 'weekly' => 'per minggu', 'monthly' => 'per bulan'];

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function bounds(CarbonImmutable $date, string $frequency): array
    {
        return match ($frequency) {
            'daily' => [$date->startOfDay(), $date->startOfDay()],
            'monthly' => [$date->startOfMonth(), $date->endOfMonth()->startOfDay()],
            default => [$date->startOfWeek(CarbonImmutable::MONDAY), $date->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay()],
        };
    }

    /**
     * Rentang yang berisi minimal satu jurnal terkirim, terbaru dulu.
     *
     * @return Collection<int, array{start: CarbonImmutable, end: CarbonImmutable, journals: Collection<int, Journal>, signoff: ?MentorSignoff}>
     */
    public static function periods(Placement $placement): Collection
    {
        $frequency = $placement->school->mentor_signoff_frequency;
        $signoffs = MentorSignoff::query()->where('placement_id', $placement->id)->get()->keyBy(fn ($s) => $s->period_start->toDateString());

        return self::submittedJournals($placement)
            ->groupBy(fn (Journal $j) => self::bounds(CarbonImmutable::parse($j->journal_date), $frequency)[0]->toDateString())
            ->map(function (Collection $journals, string $start) use ($frequency, $signoffs): array {
                [$from, $to] = self::bounds(CarbonImmutable::parse($start), $frequency);

                return ['start' => $from, 'end' => $to, 'journals' => $journals->sortBy('journal_date')->values(), 'signoff' => $signoffs->get($start)];
            })
            ->sortByDesc(fn ($p) => $p['start']->timestamp)
            ->values();
    }

    /** @return array<string, int> */
    public static function attendanceSummary(Placement $placement, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = Attendance::query()
            ->where('placement_id', $placement->id)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->countBy(fn (Attendance $a) => $a->status->value);

        return collect(AttendanceStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)])->all();
    }

    /**
     * Sidik isi yang ditandatangani. Jurnal yang berubah setelah ditandatangani menghasilkan hash berbeda.
     *
     * @param  Collection<int, Journal>  $journals
     * @param  array<string, int>  $summary
     */
    public static function contentHash(Collection $journals, array $summary): string
    {
        $payload = $journals->sortBy('id')->map(fn (Journal $j) => [
            $j->id, $j->journal_date->toDateString(), $j->work_unit, $j->activity_title,
            $j->activity_description, $j->tools_materials, $j->duration_minutes,
        ])->values()->all();

        return hash('sha256', json_encode([$payload, $summary], JSON_UNESCAPED_UNICODE));
    }

    public static function isChangedSinceSigning(MentorSignoff $signoff, Placement $placement): bool
    {
        $journals = Journal::query()->whereKey($signoff->journal_ids)->get();

        return $journals->count() !== count($signoff->journal_ids)
            || self::contentHash($journals, $signoff->attendance_summary) !== $signoff->content_hash;
    }

    /** Tanda tangan yang mencakup tanggal ini, bila ada. */
    public static function signoffCovering(Placement $placement, string $date): ?MentorSignoff
    {
        return MentorSignoff::query()
            ->where('placement_id', $placement->id)
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->first();
    }

    /** @return Collection<int, Journal> */
    private static function submittedJournals(Placement $placement): Collection
    {
        return Journal::query()
            ->where('placement_id', $placement->id)
            ->where('status', '!=', JournalStatus::Draft)
            ->orderBy('journal_date')
            ->get();
    }
}
