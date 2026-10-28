<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportTicket extends Model
{
    protected $fillable = [
        'subject',
        'body',
        'channel',
        'customer_name',
        'predicted_category',
        'predicted_priority',
        'suggested_reply',
        'confidence',
        'status',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'is_featured' => 'boolean',
        ];
    }

    /** @return HasMany<TriageRun, $this> */
    public function triageRuns(): HasMany
    {
        return $this->hasMany(TriageRun::class);
    }

    /** @return HasOne<TriageRun, $this> */
    public function latestTriageRun(): HasOne
    {
        return $this->hasOne(TriageRun::class)->latestOfMany();
    }
}
