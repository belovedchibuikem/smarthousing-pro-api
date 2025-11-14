<?php

namespace App\Models\Tenant;

use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyPaymentPlan extends Model
{
    use HasFactory;
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'property_id',
        'member_id',
        'interest_id',
        'configured_by',
        'status',
        'funding_option',
        'selected_methods',
        'configuration',
        'schedule',
        'total_amount',
        'initial_balance',
        'remaining_balance',
        'starts_on',
        'ends_on',
        'metadata',
    ];

    protected $casts = [
        'selected_methods' => 'array',
        'configuration' => 'array',
        'schedule' => 'array',
        'metadata' => 'array',
        'total_amount' => 'decimal:2',
        'initial_balance' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'starts_on' => 'datetime',
        'ends_on' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function interest(): BelongsTo
    {
        return $this->belongsTo(PropertyInterest::class, 'interest_id');
    }

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by');
    }
}


