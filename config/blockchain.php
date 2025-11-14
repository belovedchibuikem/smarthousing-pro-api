<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Blockchain Networks Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for various blockchain networks supported by the system.
    |
    */

    'networks' => [
        'ethereum' => [
            'name' => 'Ethereum Mainnet',
            'rpc_url' => env('ETHEREUM_RPC_URL', 'https://mainnet.infura.io/v3/' . env('INFURA_API_KEY')),
            'chain_id' => 1,
            'explorer_url' => 'https://etherscan.io',
            'explorer_api_key' => env('ETHERSCAN_API_KEY'),
            'explorer_api_url' => 'https://api.etherscan.io/api',
            'native_currency' => 'ETH',
            'gas_limit' => 21000,
            'gas_price_multiplier' => 1.2, // Multiply gas price by this for faster confirmation
        ],
        'polygon' => [
            'name' => 'Polygon Mainnet',
            'rpc_url' => env('POLYGON_RPC_URL', 'https://polygon-mainnet.infura.io/v3/' . env('INFURA_API_KEY')),
            'chain_id' => 137,
            'explorer_url' => 'https://polygonscan.com',
            'explorer_api_key' => env('POLYGONSCAN_API_KEY'),
            'explorer_api_url' => 'https://api.polygonscan.com/api',
            'native_currency' => 'MATIC',
            'gas_limit' => 21000,
            'gas_price_multiplier' => 1.2,
        ],
        'bsc' => [
            'name' => 'Binance Smart Chain',
            'rpc_url' => env('BSC_RPC_URL', 'https://bsc-dataseed.binance.org'),
            'chain_id' => 56,
            'explorer_url' => 'https://bscscan.com',
            'explorer_api_key' => env('BSCSCAN_API_KEY'),
            'explorer_api_url' => 'https://api.bscscan.com/api',
            'native_currency' => 'BNB',
            'gas_limit' => 21000,
            'gas_price_multiplier' => 1.2,
        ],
        'arbitrum' => [
            'name' => 'Arbitrum One',
            'rpc_url' => env('ARBITRUM_RPC_URL', 'https://arbitrum-mainnet.infura.io/v3/' . env('INFURA_API_KEY')),
            'chain_id' => 42161,
            'explorer_url' => 'https://arbiscan.io',
            'explorer_api_key' => env('ARBISCAN_API_KEY'),
            'explorer_api_url' => 'https://api.arbiscan.io/api',
            'native_currency' => 'ETH',
            'gas_limit' => 21000,
            'gas_price_multiplier' => 1.2,
        ],
        'optimism' => [
            'name' => 'Optimism',
            'rpc_url' => env('OPTIMISM_RPC_URL', 'https://optimism-mainnet.infura.io/v3/' . env('INFURA_API_KEY')),
            'chain_id' => 10,
            'explorer_url' => 'https://optimistic.etherscan.io',
            'explorer_api_key' => env('OPTIMISTIC_ETHERSCAN_API_KEY'),
            'explorer_api_url' => 'https://api-optimistic.etherscan.io/api',
            'native_currency' => 'ETH',
            'gas_limit' => 21000,
            'gas_price_multiplier' => 1.2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Smart Contract Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for smart contracts used for property registration.
    | Set these addresses after deploying your contracts.
    |
    */

    'contracts' => [
        'ethereum' => [
            'property_registry' => env('ETHEREUM_PROPERTY_REGISTRY_CONTRACT'),
            'abi' => env('ETHEREUM_PROPERTY_REGISTRY_ABI'), // JSON ABI or path to ABI file
        ],
        'polygon' => [
            'property_registry' => env('POLYGON_PROPERTY_REGISTRY_CONTRACT'),
            'abi' => env('POLYGON_PROPERTY_REGISTRY_ABI'),
        ],
        'bsc' => [
            'property_registry' => env('BSC_PROPERTY_REGISTRY_CONTRACT'),
            'abi' => env('BSC_PROPERTY_REGISTRY_ABI'),
        ],
        'arbitrum' => [
            'property_registry' => env('ARBITRUM_PROPERTY_REGISTRY_CONTRACT'),
            'abi' => env('ARBITRUM_PROPERTY_REGISTRY_ABI'),
        ],
        'optimism' => [
            'property_registry' => env('OPTIMISM_PROPERTY_REGISTRY_CONTRACT'),
            'abi' => env('OPTIMISM_PROPERTY_REGISTRY_ABI'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for blockchain event webhooks.
    |
    */

    'webhooks' => [
        'enabled' => env('BLOCKCHAIN_WEBHOOKS_ENABLED', false),
        'secret' => env('BLOCKCHAIN_WEBHOOK_SECRET'),
        'url' => env('BLOCKCHAIN_WEBHOOK_URL', '/api/webhooks/blockchain'),
    ],
];

