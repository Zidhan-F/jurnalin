<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SchoolPlan;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolPayment extends Model
{
    use BelongsToSchool, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['plan' => SchoolPlan::class, 'reviewed_at' => 'datetime', 'cycle' => 'integer',
            'student_count' => 'integer', 'unit_price' => 'integer'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PklPeriod::class, 'pkl_period_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(BillingGroup::class, 'billing_group_id');
    }

    /** Nama tagihan untuk riwayat dan antrean verifikasi. */
    public function title(): string
    {
        return match ($this->kind) {
            'upgrade' => 'Selisih naik ke Sekolah Pro',
            'monthly' => 'Pembayaran bulan '.$this->cycle,
            default => $this->group?->name ?? 'Grup',
        };
    }

    public function amount(): int
    {
        return $this->student_count * $this->unit_price * $this->months;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Menunggu verifikasi',
            'cancelled' => 'Dibatalkan',
            'verified' => 'Terverifikasi',
            'returned' => 'Perlu perbaikan',
        };
    }
}
