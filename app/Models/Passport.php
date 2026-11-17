<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passport extends Model
{
    use BelongsToSchool, HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'token' => 'encrypted',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
