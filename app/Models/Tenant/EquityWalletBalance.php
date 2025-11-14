<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquityWalletBalance extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'member_id',
        'balance',
        'total_contributed',
        'total_used',
        'currency',
        'is_active',
        'last_updated_at',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_contributed' => 'decimal:2',
        'total_used' => 'decimal:2',
        'is_active' => 'boolean',
        'last_updated_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(EquityTransaction::class, 'equity_wallet_balance_id');
    }

    public function canUse(float $amount): bool
    {
        return $this->balance >= $amount && $this->is_active;
    }

    public function use(float $amount, string $reference, string $referenceType = 'property', ?string $description = null): bool
    {
        if (!$this->canUse($amount)) {
            return false;
        }

        $balanceBefore = $this->balance;
        $this->decrement('balance', $amount);
        $this->increment('total_used', $amount);
        $this->last_updated_at = now();
        $this->save();

        // Create transaction record
        EquityTransaction::create([
            'member_id' => $this->member_id,
            'equity_wallet_balance_id' => $this->id,
            'type' => 'deposit_payment',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'reference' => $reference,
            'reference_type' => $referenceType,
            'description' => $description ?? "Payment for {$referenceType} deposit",
        ]);

        return true;
    }

    public function add(float $amount, string $reference, string $referenceType = 'contribution', ?string $description = null): void
    {
        $balanceBefore = $this->balance;
        $this->increment('balance', $amount);
        $this->increment('total_contributed', $amount);
        $this->last_updated_at = now();
        $this->save();

        // Create transaction record
        EquityTransaction::create([
            'member_id' => $this->member_id,
            'equity_wallet_balance_id' => $this->id,
            'type' => 'contribution',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'reference' => $reference,
            'reference_type' => $referenceType,
            'description' => $description ?? "Equity contribution added",
        ]);
    }
}

