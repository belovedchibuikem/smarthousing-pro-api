<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberSubscription extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'mysql';

    protected $fillable = [
        'business_id',
        'member_id',
        'package_id',
        'status',
        'start_date',
        'end_date',
        'amount_paid',
        'payment_method',
        'payment_status',
        'payment_reference',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'notes',
        'cancelled_at',
        'cancellation_reason',
        'payer_name',
        'payer_phone',
        'account_details',
        'payment_evidence',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'amount_paid' => 'decimal:2',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'payment_evidence' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'business_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(MemberSubscriptionPackage::class, 'package_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Central\SuperAdmin::class, 'approved_by');
    }

    public function isPendingApproval(): bool
    {
        return $this->payment_method === 'manual' && $this->payment_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->payment_status === 'approved' || $this->payment_status === 'completed';
    }

    public function isRejected(): bool
    {
        return $this->payment_status === 'rejected';
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->end_date->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->end_date->isPast();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
