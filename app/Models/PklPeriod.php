<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\ValueObjects\AssessmentRubric;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PklPeriod extends Model
{
    use BelongsToSchool, HasUuids;

    protected $fillable = [
        'school_id',
        'name',
        'academic_year',
        'start_date',
        'end_date',
        'min_required_days',
        'assessment_template',
        'is_active',
        'status',
        'work_days',
        'holidays',
        'late_tolerance_minutes',
        'require_location',
        'require_selfie',
        'backdate_days',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'min_required_days' => 'integer',
            'assessment_template' => 'array',
            'is_active' => 'boolean',
            'work_days' => 'array',
            'holidays' => 'array',
            'late_tolerance_minutes' => 'integer',
            'require_location' => 'boolean',
            'require_selfie' => 'boolean',
            'backdate_days' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    /** Jumlah hari kalender dari tanggal mulai sampai selesai (inklusif). */
    public function totalDays(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function billingCycles(): array
    {
        $cycles = [];
        $start = $this->start_date->toImmutable();
        $end = $this->end_date->toImmutable();
        for ($month = 0; ($from = $start->addMonthsNoOverflow($month)) <= $end; $month++) {
            $until = $start->addMonthsNoOverflow($month + 1)->subDay()->min($end);
            $cycles[$month + 1] = ['from' => $from->toDateString(), 'until' => $until->toDateString()];
        }

        return $cycles;
    }

    public function accessEndsFrom(\Carbon\CarbonImmutable $start): \Carbon\CarbonImmutable
    {
        $months = count($this->billingCycles());
        $original = $this->start_date->toImmutable();
        $exclusiveEnd = $this->end_date->toImmutable()->addDay();
        if ($original->addMonthsNoOverflow($months)->equalTo($exclusiveEnd)) {
            return $start->addMonthsNoOverflow($months)->subDay();
        }
        $lastCycle = $original->addMonthsNoOverflow($months - 1);
        $remainingDays = (int) $lastCycle->diffInDays($exclusiveEnd);

        return $start->addMonthsNoOverflow($months - 1)->addDays($remainingDays)->subDay();
    }

    /** Label durasi untuk tampilan, misalnya "1 bulan", "6 bulan", "1 tahun", atau "18 hari". */
    public function durationLabel(): string
    {
        $months = (int) round($this->start_date->diffInMonths($this->end_date->copy()->addDay()));

        if ($months < 1) {
            return $this->totalDays().' hari';
        }

        return $months % 12 === 0 ? ($months / 12).' tahun' : $months.' bulan';
    }

    /** Hari kerja ISO (1 = Senin sampai 7 = Minggu). Default Senin sampai Jumat. */
    public function workDays(): array
    {
        return array_map('intval', $this->work_days ?: [1, 2, 3, 4, 5]);
    }

    /** @return list<array{date:string, label:string}> */
    public function holidayList(): array
    {
        return array_values(array_filter($this->holidays ?? [], fn ($h) => ! empty($h['date'])));
    }

    public function isHoliday(string $date): bool
    {
        return in_array($date, array_column($this->holidayList(), 'date'), true);
    }

    /** Hari terjadwal: dalam rentang periode, termasuk hari kerja, dan bukan hari libur (P03). */
    public function isScheduledDay(\Carbon\CarbonInterface $date): bool
    {
        $d = $date->toDateString();

        return $d >= $this->start_date->toDateString() && $d <= $this->end_date->toDateString()
            && in_array($date->dayOfWeekIso, $this->workDays(), true) && ! $this->isHoliday($d);
    }

    /** @return list<string> tanggal terjadwal sampai batas (inklusif). */
    public function scheduledDates(?\Carbon\CarbonInterface $until = null): array
    {
        $dates = [];
        $end = $this->end_date->toImmutable();
        if ($until !== null && $until->toDateString() < $end->toDateString()) {
            $end = \Carbon\CarbonImmutable::parse($until->toDateString());
        }
        for ($d = $this->start_date->toImmutable(); $d <= $end; $d = $d->addDay()) {
            if ($this->isScheduledDay($d)) {
                $dates[] = $d->toDateString();
            }
        }

        return $dates;
    }

    public function lateToleranceMinutes(): int
    {
        return $this->late_tolerance_minutes ?? $this->school->late_tolerance_minutes;
    }

    public function selfieRequired(): bool
    {
        return $this->require_selfie ?? $this->school->require_attendance_photo;
    }

    public const STATUS_LABELS = ['draft' => 'Draf', 'scheduled' => 'Terjadwal', 'active' => 'Aktif', 'completed' => 'Selesai', 'cancelled' => 'Batal'];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    public function rubric(): AssessmentRubric
    {
        return AssessmentRubric::fromArray($this->assessment_template ?? AssessmentRubric::defaultTemplate());
    }

    public function classes(): HasMany
    {
        return $this->hasMany(PeriodClass::class);
    }

    public function placementGroups(): HasMany
    {
        return $this->hasMany(PlacementGroup::class);
    }

    /** Tanggal pengarsipan otomatis: 1 tahun sejak tanggal mulai (atau setelah tanggal selesai jika durasi penuh 1 tahun). */
    public function autoArchiveDate(): \Carbon\CarbonImmutable
    {
        return $this->start_date->toImmutable()->addYear()->max($this->end_date->toImmutable()->addDay());
    }

    /** Arsipkan otomatis seluruh periode aktif yang sudah melewati masa 1 tahun. */
    public static function archiveExpired(?\Carbon\CarbonInterface $today = null): int
    {
        $now = $today ? \Carbon\CarbonImmutable::parse($today->toDateString()) : \Carbon\CarbonImmutable::today();
        $archived = 0;

        self::query()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $now->subYear()->toDateString())
            ->whereDate('end_date', '<', $now->toDateString())
            ->get()
            ->each(function (self $period) use (&$archived): void {
                $period->update(['is_active' => false, 'status' => 'completed']);
                AuditLog::record('period.archived', $period, ['reason' => 'Otomatis diarsipkan setelah 1 tahun']);
                $archived++;
            });

        return $archived;
    }
}
