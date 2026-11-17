<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TracerResponse extends Model
{
    use BelongsToSchool, HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'statuses' => 'array',
            'answers' => 'array',
            'corrections' => 'array',
            'token' => 'encrypted',
            'submitted_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(TracerCampaign::class, 'tracer_campaign_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
