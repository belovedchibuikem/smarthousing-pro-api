<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'tenant_id',
        'gateway_type',
        'is_enabled',
        'is_test_mode',
        'credentials',
        'configuration',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_test_mode' => 'boolean',
        'credentials' => 'array',
        'configuration' => 'array',
    ];

    public function isActive(): bool
    {
        return $this->is_enabled;
    }

    public function isTestMode(): bool
    {
        return $this->is_test_mode;
    }
}
