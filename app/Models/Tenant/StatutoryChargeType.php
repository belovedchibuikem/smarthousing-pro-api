<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatutoryChargeType extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'type',
        'description',
        'default_amount',
        'frequency',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function charges(): HasMany
    {
        return $this->hasMany(StatutoryCharge::class, 'type', 'type');
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}
