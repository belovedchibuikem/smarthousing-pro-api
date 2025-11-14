<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mortgage extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'member_id',
        'provider_id',
        'property_id',
        'loan_amount',
        'interest_rate',
        'tenure_years',
        'monthly_payment',
        'status',
        'schedule_approved',
        'schedule_approved_at',
        'application_date',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
        'notes',
    ];

    protected $casts = [
        'loan_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'schedule_approved' => 'boolean',
        'application_date' => 'datetime',
        'approved_at' => 'datetime',
        'schedule_approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(MortgageProvider::class, 'provider_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
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

    public function repayments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MortgageRepayment::class);
    }

    public function getTotalPrincipalRepaid(): float
    {
        return $this->repayments()->where('status', 'paid')->sum('principal_paid');
    }

    public function getTotalInterestPaid(): float
    {
        return $this->repayments()->where('status', 'paid')->sum('interest_paid');
    }

    public function getRemainingPrincipal(): float
    {
        return max(0, $this->loan_amount - $this->getTotalPrincipalRepaid());
    }

    public function isFullyRepaid(): bool
    {
        return $this->getRemainingPrincipal() <= 0;
    }
}

