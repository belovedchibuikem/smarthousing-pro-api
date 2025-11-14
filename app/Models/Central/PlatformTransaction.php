<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Central\SuperAdmin;

class PlatformTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'reference',
        'type',
        'amount',
        'currency',
        'status',
        'payment_gateway',
        'gateway_reference',
        'metadata',
        'paid_at',
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
    ];

    protected $casts = [
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'approved_at' => 'datetime',
        'payment_date' => 'datetime',
        'payment_evidence' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'approved_by');
    }

    // Helper methods
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
        return $this->payment_gateway === 'manual';
    }

    public function requiresApproval(): bool
    {
        return $this->isManualPayment() && $this->approval_status === 'pending';
    }
}
