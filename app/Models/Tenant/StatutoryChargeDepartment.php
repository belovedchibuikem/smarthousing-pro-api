<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatutoryChargeDepartment extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all charges for this department
     */
    public function charges(): HasMany
    {
        return $this->hasMany(StatutoryCharge::class, 'department_id');
    }

    /**
     * Get the count of charges in this department
     */
    public function getChargeCountAttribute(): int
    {
        return $this->charges()->count();
    }

    /**
     * Get total allocated amount
     */
    public function getTotalAllocatedAttribute(): float
    {
        return (float) $this->charges()->sum('amount');
    }

    /**
     * Get total collected amount
     */
    public function getTotalCollectedAttribute(): float
    {
        return (float) $this->charges()->where('status', 'paid')->sum('amount');
    }

    /**
     * Get collection rate
     */
    public function getCollectionRateAttribute(): float
    {
        $allocated = $this->total_allocated;
        if ($allocated <= 0) {
            return 0;
        }
        return (($this->total_collected / $allocated) * 100);
    }
}

