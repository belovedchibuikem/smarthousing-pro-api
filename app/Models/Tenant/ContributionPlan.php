<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContributionPlan extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'description',
        'amount',
        'minimum_amount',
        'frequency',
        'is_mandatory',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'plan_id');
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}

