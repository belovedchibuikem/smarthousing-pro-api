<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'mysql';
    protected $table = 'platform_payment_gateways';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_active',
        'settings',
        'supported_currencies',
        'supported_countries',
        'transaction_fee_percentage',
        'transaction_fee_fixed',
        'minimum_amount',
        'maximum_amount',
        'platform_fee_percentage',
        'platform_fee_fixed',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'supported_currencies' => 'array',
        'supported_countries' => 'array',
        'transaction_fee_percentage' => 'decimal:2',
        'transaction_fee_fixed' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_amount' => 'decimal:2',
        'platform_fee_percentage' => 'decimal:2',
        'platform_fee_fixed' => 'decimal:2',
    ];
}
