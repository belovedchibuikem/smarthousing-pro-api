<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionAutoPaySetting extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'member_id',
        'is_enabled',
        'payment_method',
        'amount',
        'day_of_month',
        'metadata',
        'card_reference',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}

