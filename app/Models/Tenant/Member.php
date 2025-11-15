<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Tenant\PropertyInterest;
use App\Models\Tenant\PropertyAllocation;

class Member extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'contribution_plan_id',
        'member_number',
        'staff_id',
        'ippis_number',
        'date_of_birth',
        'gender',
        'marital_status',
        'nationality',
        'state_of_origin',
        'lga',
        'residential_address',
        'city',
        'state',
        'rank',
        'department',
        'command_state',
        'employment_date',
        'years_of_service',
        'membership_type',
        'kyc_status',
        'kyc_submitted_at',
        'kyc_verified_at',
        'kyc_rejection_reason',
        'kyc_documents',
        'next_of_kin_name',
        'next_of_kin_relationship',
        'next_of_kin_phone',
        'next_of_kin_email',
        'next_of_kin_address',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'employment_date' => 'date',
        'kyc_submitted_at' => 'datetime',
        'kyc_verified_at' => 'datetime',
        'kyc_documents' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contributionPlan(): BelongsTo
    {
        return $this->belongsTo(ContributionPlan::class);
    }


    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function equityContributions(): HasMany
    {
        return $this->hasMany(EquityContribution::class);
    }

    public function equityWalletBalance(): HasOne
    {
        return $this->hasOne(EquityWalletBalance::class);
    }

    public function equityTransactions(): HasMany
    {
        return $this->hasMany(EquityTransaction::class);
    }

    public function propertyInterests(): HasMany
    {
        return $this->hasMany(PropertyInterest::class);
    }

    public function propertyAllocations(): HasMany
    {
        return $this->hasMany(PropertyAllocation::class);
    }

    public function contributionAutoPaySetting(): HasOne
    {
        return $this->hasOne(ContributionAutoPaySetting::class);
    }

    public function isKycVerified(): bool
    {
        return $this->kyc_status === 'verified';
    }

    public function isKycPending(): bool
    {
        return $this->kyc_status === 'pending' || $this->kyc_status === 'submitted';
    }

    public function getFullNameAttribute(): string
    {
        return $this->user->first_name . ' ' . $this->user->last_name;
    }

    /**
     * Check if member has active subscription
     * Note: This queries the central database (MemberSubscription)
     */
    public function hasActiveSubscription(): bool
    {
        $tenantId = tenant('id');
        if (!$tenantId) {
            return false;
        }

        return \App\Models\Central\MemberSubscription::where('business_id', $tenantId)
            ->where('member_id', $this->id)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->where(function($query) {
                $query->where('payment_status', 'approved')
                      ->orWhere('payment_status', 'completed');
            })
            ->exists();
    }

    /**
     * Get active subscription
     */
    public function getActiveSubscription()
    {
        $tenantId = tenant('id');
        if (!$tenantId) {
            return null;
        }

        return \App\Models\Central\MemberSubscription::where('business_id', $tenantId)
            ->where('member_id', $this->id)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->where(function($query) {
                $query->where('payment_status', 'approved')
                      ->orWhere('payment_status', 'completed');
            })
            ->first();
    }
}
