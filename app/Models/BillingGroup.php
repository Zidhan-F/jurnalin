<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingGroup extends Model
{
    use BelongsToSchool, HasUuids;

    protected $guarded = ['id'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'billing_group_members');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PklPeriod::class, 'pkl_period_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SchoolPayment::class);
    }

    public function grant(CarbonImmutable $start, CarbonImmutable $end): void
    {
        $this->update(['starts_on' => $start->toDateString(), 'paid_until' => $end->toDateString(),
            'journal_until' => $end->addDay()->addMonthNoOverflow()->subDay()->toDateString(), 'stopped' => false]);
    }

    public function statusLabel(string $today): string
    {
        return match (true) {
            (bool) $this->stopped => 'Dinonaktifkan',
            ! $this->starts_on => 'Belum aktif',
            $today < $this->starts_on => 'Terjadwal',
            $today <= $this->paid_until => 'Aktif',
            $today <= $this->journal_until => 'Khusus jurnal',
            default => 'Akses berakhir',
        };
    }
}
