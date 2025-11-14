<?php

namespace App\Models\Tenant;

use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalMortgagePlan extends Model
{
    use HasFactory;
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'property_id',
        'member_id',
        'configured_by',
        'title',
        'description',
        'principal',
        'interest_rate',
        'tenure_months',
        'monthly_payment',
        'frequency',
        'status',
        'schedule_approved',
        'schedule_approved_at',
        'starts_on',
        'ends_on',
        'schedule',
        'metadata',
    ];

    protected $casts = [
        'principal' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'monthly_payment' => 'decimal:2',
        'schedule_approved' => 'boolean',
        'starts_on' => 'datetime',
        'ends_on' => 'datetime',
        'schedule_approved_at' => 'datetime',
        'schedule' => 'array',
        'metadata' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by');
    }

    public function repayments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InternalMortgageRepayment::class, 'internal_mortgage_plan_id');
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
        return max(0, $this->principal - $this->getTotalPrincipalRepaid());
    }

    public function isFullyRepaid(): bool
    {
        return $this->getRemainingPrincipal() <= 0;
    }
}

