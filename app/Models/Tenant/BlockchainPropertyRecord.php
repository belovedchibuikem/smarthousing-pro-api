<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockchainPropertyRecord extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'property_id',
        'blockchain_hash',
        'transaction_hash',
        'status',
        'property_data',
        'ownership_data',
        'network',
        'contract_address',
        'token_id',
        'gas_fee',
        'gas_price',
        'block_number',
        'registered_at',
        'confirmed_at',
        'failed_at',
        'failure_reason',
        'verification_notes',
        'registered_by',
        'verified_by',
    ];

    protected $casts = [
        'ownership_data' => 'array',
        'gas_fee' => 'decimal:8',
        'gas_price' => 'decimal:8',
        'registered_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function registrant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getBlockchainExplorerUrlAttribute(): ?string
    {
        if (!$this->transaction_hash || !$this->network) {
            return null;
        }

        $explorerUrls = [
            'ethereum' => 'https://etherscan.io/tx/',
            'polygon' => 'https://polygonscan.com/tx/',
            'bsc' => 'https://bscscan.com/tx/',
            'arbitrum' => 'https://arbiscan.io/tx/',
            'optimism' => 'https://optimistic.etherscan.io/tx/',
        ];

        $baseUrl = $explorerUrls[$this->network] ?? 'https://etherscan.io/tx/';
        return $baseUrl . $this->transaction_hash;
    }

    public function getOwnersCountAttribute(): int
    {
        return is_array($this->ownership_data) ? count($this->ownership_data) : 0;
    }
}

