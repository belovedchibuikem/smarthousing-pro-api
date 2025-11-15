<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasFactory, HasDomains, HasDatabase;

    protected $connection = 'mysql'; // Central database

    protected $fillable = [
        'id',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Get custom domain requests for this tenant
     */
    public function customDomainRequests()
    {
        return $this->hasMany(CustomDomainRequest::class);
    }

    /**
     * Get subscription for this tenant
     */
    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Get platform transactions for this tenant
     */
    public function transactions()
    {
        return $this->hasMany(PlatformTransaction::class);
    }

    /**
     * Check if tenant has active subscription
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();
    }

    /**
     * Get active subscription
     */
    public function getActiveSubscription()
    {
        return $this->subscription()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->first();
    }

    /**
     * Check if tenant is on trial
     */
    public function isOnTrial(): bool
    {
        $subscription = $this->subscription;
        return $subscription && $subscription->trial_ends_at && $subscription->trial_ends_at->isFuture();
    }
}
