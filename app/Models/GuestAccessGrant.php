<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestAccessGrant extends Model
{
    use BelongsToSchool, HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['token', 'token_hash', 'otp_hash'];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'token' => 'encrypted',
            'can_review' => 'boolean',
            'can_evaluate' => 'boolean',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'otp_verified_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(PartnerContact::class, 'partner_contact_id');
    }
}
