<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockchainSetting extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'primary_network',
        'is_enabled',
        'setup_completed',
        'ethereum_rpc_url',
        'polygon_rpc_url',
        'bsc_rpc_url',
        'arbitrum_rpc_url',
        'optimism_rpc_url',
        'etherscan_api_key',
        'polygonscan_api_key',
        'bscscan_api_key',
        'arbiscan_api_key',
        'optimistic_etherscan_api_key',
        'ethereum_contract_address',
        'polygon_contract_address',
        'bsc_contract_address',
        'arbitrum_contract_address',
        'optimism_contract_address',
        'webhooks_enabled',
        'webhook_secret',
        'webhook_url',
        'gas_price_multiplier',
        'default_gas_limit',
        'setup_completed_by',
        'setup_completed_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'setup_completed' => 'boolean',
        'webhooks_enabled' => 'boolean',
        'gas_price_multiplier' => 'decimal:2',
        'setup_completed_at' => 'datetime',
    ];

    protected $hidden = [
        'webhook_secret',
        'etherscan_api_key',
        'polygonscan_api_key',
        'bscscan_api_key',
        'arbiscan_api_key',
        'optimistic_etherscan_api_key',
    ];

    /**
     * Get singleton instance for tenant
     */
    public static function getInstance(): self
    {
        $instance = self::first();
        
        if (!$instance) {
            $instance = self::create([
                'primary_network' => 'ethereum',
                'is_enabled' => false,
                'setup_completed' => false,
            ]);
        }
        
        return $instance;
    }

    /**
     * Get RPC URL for a network (uses tenant-specific or falls back to config)
     */
    public function getRpcUrl(string $network): ?string
    {
        $attribute = "{$network}_rpc_url";
        
        // Check tenant-specific setting first
        if ($this->$attribute) {
            return $this->$attribute;
        }
        
        // Fall back to global config
        return config("blockchain.networks.{$network}.rpc_url");
    }

    /**
     * Get explorer API key for a network
     */
    public function getExplorerApiKey(string $network): ?string
    {
        $keys = [
            'ethereum' => $this->etherscan_api_key,
            'polygon' => $this->polygonscan_api_key,
            'bsc' => $this->bscscan_api_key,
            'arbitrum' => $this->arbiscan_api_key,
            'optimism' => $this->optimistic_etherscan_api_key,
        ];
        
        if (isset($keys[$network]) && $keys[$network]) {
            return $keys[$network];
        }
        
        // Fall back to config
        return config("blockchain.networks.{$network}.explorer_api_key");
    }

    /**
     * Get contract address for a network
     */
    public function getContractAddress(string $network): ?string
    {
        $attribute = "{$network}_contract_address";
        
        if ($this->$attribute) {
            return $this->$attribute;
        }
        
        return config("blockchain.contracts.{$network}.property_registry");
    }

    public function setupCompleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'setup_completed_by');
    }
}

