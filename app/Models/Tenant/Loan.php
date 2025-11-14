<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'member_id',
        'product_id',
        'property_id',
        'amount',
        'interest_rate',
        'duration_months',
        'type',
        'purpose',
        'status',
        'application_date',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
        'monthly_payment',
        'total_amount',
        'interest_amount',
        'processing_fee',
        'required_documents',
        'application_metadata',
        'disbursed_at',
        'disbursed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'application_date' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'monthly_payment' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'required_documents' => 'array',
        'application_metadata' => 'array',
        'disbursed_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'product_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
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
        return max(0, $this->amount - $this->getTotalPrincipalRepaid());
    }

    public function isFullyRepaid(): bool
    {
        return $this->getRemainingPrincipal() <= 0;
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

    public function getTotalAmountAttribute(): float
    {
        return $this->amount + ($this->amount * $this->interest_rate / 100);
    }

    public function getMonthlyPaymentAttribute(): float
    {
        if ($this->duration_months <= 0) {
            return 0;
        }
        
        return $this->total_amount / $this->duration_months;
    }
}
