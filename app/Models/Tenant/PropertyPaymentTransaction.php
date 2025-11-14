<?php

namespace App\Models\Tenant;

use App\Models\Tenant\InternalMortgagePlan;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PropertyPaymentPlan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyPaymentTransaction extends Model
{
    use HasFactory;
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'property_id',
        'member_id',
        'payment_id',
        'plan_id',
        'mortgage_plan_id',
        'source',
        'amount',
        'direction',
        'reference',
        'status',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PropertyPaymentPlan::class, 'plan_id');
    }

    public function mortgagePlan(): BelongsTo
    {
        return $this->belongsTo(InternalMortgagePlan::class, 'mortgage_plan_id');
    }
}


