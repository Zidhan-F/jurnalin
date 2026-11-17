<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Enums\JournalStatus;
use App\Models\Attendance;
use App\Models\Journal;
use App\Models\LearningTarget;
use App\Models\Placement;
use App\Models\SupportTicket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Kalender (P10), Cek Kelengkapan (P18), Pemantauan Pintar (P19), dan Celah Kompetensi (P24).
 * Semua dihitung dari data nyata. Izin yang disetujui dan hari libur tidak dihitung sebagai kekurangan,
 * dan sistem tidak memberi label negatif atau menyatakan lulus secara otomatis.
 */
final class PklMonitor
{
    public const ABSENT_STREAK = 2;

    public const STALE_REVIEW_DAYS = 3;

    /** @return list<array{date:string, day:int, scheduled:bool, holiday:?string, attendance:?Attendance, journals:Collection, state:string, today:bool}> */
    public static function calendar(Placement $placement, CarbonImmutable $month, string $today): array
    {
        $period = $placement->pklPeriod;
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();
        $attendances = Attendance::query()->where('placement_id', $placement->id)->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])->get()->keyBy(fn ($a) => $a->attendance_date->toDateString());
        $journals = Journal::query()->where('placement_id', $placement->id)->whereBetween('journal_date', [$start->toDateString(), $end->toDateString()])->get()->groupBy(fn ($j) => $j->journal_date->toDateString());
        $holidays = collect($period->holidayList())->keyBy('date');

        $days = [];
        for ($d = $start; $d <= $end; $d = $d->addDay()) {
            $date = $d->toDateString();
            $att = $attendances[$date] ?? null;
            $js = $journals[$date] ?? collect();
            $scheduled = $period->isScheduledDay($d);
            $state = match (true) {
                $holidays->has($date) => 'holiday',
                ! $scheduled => 'off',
                $att && in_array($att->status, [AttendanceStatus::Sick, AttendanceStatus::Permission], true) => 'leave',
                $js->contains(fn ($j) => in_array($j->status, [JournalStatus::Submitted, JournalStatus::RevisionNeeded], true)) => 'pending',
                $js->contains(fn ($j) => $j->status === JournalStatus::Approved) => 'approved',
                $date > $today => 'future',
                $att === null && $js->isEmpty() => 'missing',
                default => 'recorded',
            };
            $days[] = ['date' => $date, 'day' => $d->day, 'dow' => $d->dayOfWeekIso, 'scheduled' => $scheduled, 'holiday' => $holidays[$date]['label'] ?? null,
                'attendance' => $att, 'journals' => $js, 'state' => $state, 'today' => $date === $today];
        }

        return $days;
    }

    /**
     * Checklist penyelesaian (P18). Pemilik tindakan dicatat; keputusan akhir tetap pada sekolah.
     *
     * @return list<array{label:string, done:bool, owner:string, detail:string}>
     */
    public static function readiness(Placement $placement, string $today): array
    {
        $period = $placement->pklPeriod;
        $scheduled = $period->scheduledDates(CarbonImmutable::parse($today));
        $leaveDates = Attendance::query()->where('placement_id', $placement->id)->whereIn('status', [AttendanceStatus::Sick->value, AttendanceStatus::Permission->value])->whereNotNull('approved_by_teacher_id')
            ->pluck('attendance_date')->map(fn ($d) => $d->toDateString())->all();
        $journalDates = Journal::query()->where('placement_id', $placement->id)->whereIn('status', [JournalStatus::Submitted->value, JournalStatus::Approved->value])
            ->pluck('journal_date')->map(fn ($d) => $d->toDateString())->unique()->all();
        $required = array_values(array_diff($scheduled, $leaveDates));
        $covered = count(array_intersect($required, $journalDates));
        $revisions = Journal::query()->where('placement_id', $placement->id)->where('status', JournalStatus::RevisionNeeded)->count();
        $pending = Journal::query()->where('placement_id', $placement->id)->where('status', JournalStatus::Submitted)->count();
        $assessment = $placement->assessment;
        $signed = $placement->mentorSignoffs()->count() > 0 || \App\Models\MentorEvaluation::query()->where('placement_id', $placement->id)->where('status', 'final')->exists() || ($assessment?->industry_form_photo_path !== null);
        $attended = Attendance::query()->where('placement_id', $placement->id)->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])->count();

        return [
            ['label' => 'Jurnal pada hari terjadwal', 'done' => $required !== [] && $covered >= count($required), 'owner' => 'Siswa', 'detail' => "{$covered} dari ".count($required).' hari terjadwal (izin disetujui & libur tidak dihitung)'],
            ['label' => 'Tidak ada jurnal yang perlu revisi', 'done' => $revisions === 0, 'owner' => 'Siswa', 'detail' => $revisions ? "{$revisions} jurnal menunggu perbaikan" : 'Bersih'],
            ['label' => 'Jurnal sudah ditinjau guru', 'done' => $pending === 0, 'owner' => 'Guru pembimbing', 'detail' => $pending ? "{$pending} jurnal menunggu review" : 'Semua sudah ditinjau'],
            ['label' => 'Target hari hadir', 'done' => $attended >= $period->min_required_days, 'owner' => 'Siswa', 'detail' => "{$attended} dari {$period->min_required_days} hari hadir"],
            ['label' => 'Nilai industri tersedia', 'done' => $signed, 'owner' => 'Pembimbing lapangan / guru', 'detail' => $signed ? 'Formulir, tanda tangan, atau evaluasi tercatat' : 'Belum ada formulir atau evaluasi pembimbing lapangan'],
            ['label' => 'Nilai akhir final', 'done' => $assessment?->finalized_at !== null, 'owner' => 'Guru pembimbing', 'detail' => $assessment?->finalized_at ? 'Final versi '.$assessment->version : 'Belum difinalkan'],
        ];
    }

    /**
     * Peringatan tindak lanjut (P19). Kondisi izin/libur sudah dipertimbangkan; bahasa netral, bukan vonis.
     *
     * @param  Collection<int, Placement>  $placements
     * @return Collection<int, array{placement:Placement, type:string, urgency:string, message:string}>
     */
    public static function alerts(Collection $placements, string $today): Collection
    {
        $alerts = collect();
        $ids = $placements->pluck('id');
        $staleCutoff = CarbonImmutable::parse($today)->subDays(self::STALE_REVIEW_DAYS)->toDateString();
        $stale = Journal::query()->whereIn('placement_id', $ids)->where('status', JournalStatus::Submitted)->whereDate('journal_date', '<=', $staleCutoff)->get()->groupBy('placement_id');
        $flagged = Attendance::query()->whereIn('placement_id', $ids)->where('review_status', 'pending')->get()->groupBy('placement_id');
        $tickets = SupportTicket::query()->whereIn('placement_id', $ids)->where('recipient', 'teacher')->where('status', '!=', 'resolved')->get()->groupBy('placement_id');
        $overdueTargets = LearningTarget::query()->whereIn('placement_id', $ids)->where('status', 'open')->whereDate('due_date', '<', $today)->get()->groupBy('placement_id');

        foreach ($placements as $p) {
            $period = $p->pklPeriod;
            $recent = collect($period->scheduledDates(CarbonImmutable::parse($today)->subDay()))->reverse()->take(5)->values();
            $recorded = Attendance::query()->where('placement_id', $p->id)->whereIn('attendance_date', $recent->all())->pluck('attendance_date')->map(fn ($d) => $d->toDateString())->all();
            $streak = 0;
            foreach ($recent as $d) {
                if (in_array($d, $recorded, true)) {
                    break;
                }
                $streak++;
            }
            if ($streak >= self::ABSENT_STREAK) {
                $alerts->push(['placement' => $p, 'type' => 'absent_streak', 'urgency' => $streak >= 4 ? 'tinggi' : 'sedang', 'message' => "Belum ada presensi pada {$streak} hari kerja terakhir (izin dan libur sudah dikecualikan). Hubungi siswa untuk memastikan kondisinya."]);
            }
            if ($flagged->has($p->id)) {
                $alerts->push(['placement' => $p, 'type' => 'flagged', 'urgency' => 'sedang', 'message' => $flagged[$p->id]->count().' presensi bertanda menunggu tinjauan. Tanda bukan bukti pelanggaran.']);
            }
            if ($stale->has($p->id)) {
                $alerts->push(['placement' => $p, 'type' => 'stale_review', 'urgency' => 'sedang', 'message' => $stale[$p->id]->count().' jurnal menunggu review lebih dari '.self::STALE_REVIEW_DAYS.' hari.']);
            }
            if ($tickets->has($p->id)) {
                $alerts->push(['placement' => $p, 'type' => 'help', 'urgency' => 'tinggi', 'message' => 'Siswa meminta bantuan lewat Lapor ke Guru.']);
            }
            if ($overdueTargets->has($p->id)) {
                $alerts->push(['placement' => $p, 'type' => 'target', 'urgency' => 'rendah', 'message' => $overdueTargets[$p->id]->count().' target belajar melewati tenggat tanpa bukti.']);
            }
        }

        return $alerts->sortBy(fn ($a) => ['tinggi' => 0, 'sedang' => 1, 'rendah' => 2][$a['urgency']])->values();
    }

    /** Ringkasan minggu berjalan: kelengkapan jurnal terhadap hari terjadwal (bukan nilai). */
    public static function weekly(Collection $placements, string $today): array
    {
        $monday = CarbonImmutable::parse($today)->startOfWeek();
        $required = 0;
        $covered = 0;
        foreach ($placements as $p) {
            $days = array_values(array_filter($p->pklPeriod->scheduledDates(CarbonImmutable::parse($today)), fn ($d) => $d >= $monday->toDateString()));
            $required += count($days);
            $covered += Journal::query()->where('placement_id', $p->id)->whereIn('journal_date', $days)->distinct()->count('journal_date');
        }

        return ['required' => $required, 'covered' => $covered, 'percent' => $required ? (int) round($covered / $required * 100) : 100, 'from' => $monday->toDateString()];
    }
}
