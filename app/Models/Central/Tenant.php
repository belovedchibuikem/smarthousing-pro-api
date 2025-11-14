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

    // Basic tenant functionality - additional methods can be added as needed
}
