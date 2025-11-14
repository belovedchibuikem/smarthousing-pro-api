<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatutoryChargePayment extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'statutory_charge_id',
        'amount',
        'payment_method',
        'reference',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function statutoryCharge(): BelongsTo
    {
        return $this->belongsTo(StatutoryCharge::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
