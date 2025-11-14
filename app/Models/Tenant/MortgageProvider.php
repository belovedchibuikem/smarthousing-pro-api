<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MortgageProvider extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'description',
        'contact_email',
        'contact_phone',
        'website',
        'address',
        'interest_rate_min',
        'interest_rate_max',
        'min_loan_amount',
        'max_loan_amount',
        'min_tenure_years',
        'max_tenure_years',
        'requirements',
        'is_active',
    ];

    protected $casts = [
        'interest_rate_min' => 'decimal:2',
        'interest_rate_max' => 'decimal:2',
        'min_loan_amount' => 'decimal:2',
        'max_loan_amount' => 'decimal:2',
        'requirements' => 'array',
        'is_active' => 'boolean',
    ];

    public function mortgages(): HasMany
    {
        return $this->hasMany(Mortgage::class, 'provider_id');
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}

