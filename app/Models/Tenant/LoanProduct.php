<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanProduct extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'description',
        'min_amount',
        'max_amount',
        'interest_rate',
        'min_tenure_months',
        'max_tenure_months',
        'interest_type',
        'eligibility_criteria',
        'required_documents',
        'is_active',
        'processing_fee_percentage',
        'late_payment_fee',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'eligibility_criteria' => 'array',
        'required_documents' => 'array',
        'is_active' => 'boolean',
        'processing_fee_percentage' => 'integer',
        'late_payment_fee' => 'decimal:2',
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'product_id');
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function calculateInterest(float $amount, int $months): float
    {
        if ($this->interest_type === 'compound') {
            return $amount * pow(1 + ($this->interest_rate / 100), $months) - $amount;
        }
        
        return $amount * ($this->interest_rate / 100) * $months;
    }

    public function calculateMonthlyPayment(float $amount, int $months): float
    {
        $totalAmount = $amount + $this->calculateInterest($amount, $months);
        return $totalAmount / $months;
    }
}
