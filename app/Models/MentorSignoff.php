<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tanda tangan pembimbing industri (DUDI) untuk satu rentang hari/minggu/bulan jurnal. */
class MentorSignoff extends Model
{
    use BelongsToSchool, HasUuids, \App\Models\Concerns\RespectsGroupAccess;

    protected $fillable = [
        'school_id',
        'placement_id',
        'period_start',
        'period_end',
        'signer_name',
        'signer_title',
        'signature_path',
        'content_hash',
        'journal_ids',
        'attendance_summary',
        'ip',
        'user_agent',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'journal_ids' => 'array',
            'attendance_summary' => 'array',
            'signed_at' => 'datetime',
        ];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }
}
