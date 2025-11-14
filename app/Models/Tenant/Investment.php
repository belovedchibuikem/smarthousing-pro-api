<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Investment extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'member_id',
        'amount',
        'type',
        'duration_months',
        'expected_return_rate',
        'status',
        'investment_date',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expected_return_rate' => 'decimal:2',
        'investment_date' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(InvestmentReturn::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getExpectedReturnAttribute(): float
    {
        return $this->amount * ($this->expected_return_rate / 100);
    }

    public function getTotalReturnAttribute(): float
    {
        return $this->returns()->sum('amount');
    }
}
