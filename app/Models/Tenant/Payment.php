<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenant\User;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'reference',
        'amount',
        'currency',
        'payment_method',
        'status',
        'description',
        'gateway_reference',
        'gateway_url',
        'gateway_response',
        'metadata',
        'completed_at',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejection_reason',
        'bank_reference',
        'bank_name',
        'account_number',
        'account_name',
        'payment_date',
        'payment_evidence',
        'payer_name',
        'payer_phone',
        'account_details',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'gateway_response' => 'array',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'payment_date' => 'datetime',
        'payment_evidence' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Helper methods for approval workflow
    public function isPendingApproval(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    public function isManualPayment(): bool
    {
        return $this->payment_method === 'bank_transfer' || $this->payment_method === 'manual';
    }

    public function requiresApproval(): bool
    {
        return $this->isManualPayment() && $this->approval_status === 'pending';
    }
}
