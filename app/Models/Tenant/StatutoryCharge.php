<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatutoryCharge extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'member_id',
        'department_id',
        'type',
        'amount',
        'description',
        'due_date',
        'status',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(StatutoryChargeDepartment::class);
    }

    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(StatutoryChargeType::class, 'type', 'type');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StatutoryChargePayment::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        if (!$this->due_date) {
            return false;
        }
        return $this->due_date->isPast() && $this->status !== 'paid';
    }

    public function getTotalPaidAttribute(): float
    {
        // Use loaded relationship if available to avoid N+1 queries
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->sum('amount');
        }
        
        return (float) $this->payments()->sum('amount');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->amount - $this->total_paid);
    }
}
