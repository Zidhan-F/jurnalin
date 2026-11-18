<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\JournalStatus;
use App\Enums\PlacementStatus;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\Teacher;
use Illuminate\Support\Collection;

/** Ringkasan dashboard guru pembimbing (poster): progres peserta dan jurnal terbaru yang perlu tindakan. */
final class TeacherDashboard
{
    /**
     * Penempatan guru di periode aktif dalam tiga kelompok tanpa tumpang tindih:
     * selesai, aktif dengan peringatan PklMonitor, dan aktif tanpa peringatan.
     *
     * @return array{done: int, running: int, attention: int}
     */
    public static function progress(Teacher $teacher, string $today): array
    {
        $placements = Placement::query()->with('pklPeriod')->where('teacher_id', $teacher->id)
            ->whereIn('status', [PlacementStatus::Active, PlacementStatus::Completed])
            ->whereHas('pklPeriod', fn ($q) => $q->where('is_active', true))
            ->get();
        $active = $placements->where('status', PlacementStatus::Active);
        $attention = $active->isEmpty() ? 0 : PklMonitor::alerts($active, $today)->pluck('placement.id')->unique()->count();

        return [
            'done' => $placements->where('status', PlacementStatus::Completed)->count(),
            'running' => $active->count() - $attention,
            'attention' => $attention,
        ];
    }

    /** Jurnal menunggu review atau perlu revisi dari penempatan guru, terbaru dulu. */
    public static function recentJournals(Teacher $teacher, int $limit = 5): Collection
    {
        return Journal::query()->with(['student.user', 'placement'])
            ->whereIn('status', [JournalStatus::Submitted, JournalStatus::RevisionNeeded])
            ->whereHas('placement', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->latest('journal_date')->limit($limit)->get();
    }
}
