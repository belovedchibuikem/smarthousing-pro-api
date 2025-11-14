<?php

namespace App\Models\Tenant;

use App\Models\Tenant\PropertyPaymentPlan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PropertyInterest extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'property_id',
        'member_id',
        'interest_type',
        'message',
        'status',
        'priority',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
        'applicant_snapshot',
        'next_of_kin_snapshot',
        'net_salary',
        'has_existing_loan',
        'existing_loan_types',
        'property_snapshot',
        'funding_option',
        'funding_breakdown',
        'preferred_payment_methods',
        'documents',
        'signature_path',
        'signed_at',
        'mortgage_preferences',
        'mortgage_flagged',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'signed_at' => 'datetime',
        'applicant_snapshot' => 'array',
        'next_of_kin_snapshot' => 'array',
        'existing_loan_types' => 'array',
        'property_snapshot' => 'array',
        'funding_breakdown' => 'array',
        'preferred_payment_methods' => 'array',
        'documents' => 'array',
        'mortgage_preferences' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function paymentPlan(): HasOne
    {
        return $this->hasOne(PropertyPaymentPlan::class, 'interest_id');
    }
}
