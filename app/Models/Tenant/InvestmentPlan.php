<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentPlan extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'description',
        'min_amount',
        'max_amount',
        'expected_return_rate',
        'min_duration_months',
        'max_duration_months',
        'return_type',
        'risk_level',
        'features',
        'terms_and_conditions',
        'is_active',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'expected_return_rate' => 'decimal:2',
        'features' => 'array',
        'terms_and_conditions' => 'array',
        'is_active' => 'boolean',
    ];

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class, 'plan_id');
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function calculateExpectedReturn(float $amount, int $months): float
    {
        return $amount * ($this->expected_return_rate / 100) * ($months / 12);
    }

    public function getRiskColorAttribute(): string
    {
        return match($this->risk_level) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'red',
            default => 'gray'
        };
    }
}
