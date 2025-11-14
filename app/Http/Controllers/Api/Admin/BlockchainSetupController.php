<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\BlockchainSetting;
use App\Models\Tenant\BlockchainWallet;
use App\Services\Web3RpcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Exception;

class BlockchainSetupController extends Controller
{
    protected Web3RpcService $rpcService;

    public function __construct(Web3RpcService $rpcService)
    {
        
        $this->rpcService = $rpcService;
    }

    /**
     * Get current setup status
     */
    public function status(): JsonResponse
    {
        try {
            $user = request()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            
            $settings = BlockchainSetting::getInstance();
            $wallets = BlockchainWallet::where('is_active', true)->get();
            
            $primaryNetwork = $settings->primary_network ?? 'ethereum';
            $hasDefaultWallet = false;
            
            try {
                if ($primaryNetwork) {
                    $defaultWallet = BlockchainWallet::getDefaultForNetwork($primaryNetwork);
                    $hasDefaultWallet = $defaultWallet !== null;
                }
            } catch (Exception $e) {
                Log::warning('Failed to check default wallet for network ' . $primaryNetwork . ': ' . $e->getMessage());
                $hasDefaultWallet = false;
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'setup_completed' => $settings->setup_completed ?? false,
                    'is_enabled' => $settings->is_enabled ?? false,
                    'primary_network' => $primaryNetwork,
                    'wallets_count' => $wallets->count(),
                    'has_default_wallet' => $hasDefaultWallet,
                    'settings' => $settings,
                    'wallets' => $wallets->map(function($wallet) {
                        return [
                            'id' => $wallet->id,
                            'name' => $wallet->name,
                            'network' => $wallet->network,
                            'address' => $wallet->address,
                            'is_default' => $wallet->is_default,
                            'balance' => (float) $wallet->balance,
                        ];
                    }),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Blockchain setup status error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve blockchain setup status',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while retrieving setup status',
            ], 500);
        }
    }

    /**
     * Step 1: Configure network settings
     */
    public function step1_NetworkSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'primary_network' => 'required|string|in:ethereum,polygon,bsc,arbitrum,optimism',
            'ethereum_rpc_url' => 'nullable|url',
            'polygon_rpc_url' => 'nullable|url',
            'bsc_rpc_url' => 'nullable|url',
            'arbitrum_rpc_url' => 'nullable|url',
            'optimism_rpc_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $settings = BlockchainSetting::getInstance();
            $settings->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Network settings saved',
                'data' => $settings
            ]);
        } catch (Exception $e) {
            Log::error('Blockchain setup step 1 error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save network settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Step 2: Configure explorer API keys
     */
    public function step2_ExplorerApiKeys(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'etherscan_api_key' => 'nullable|string',
            'polygonscan_api_key' => 'nullable|string',
            'bscscan_api_key' => 'nullable|string',
            'arbiscan_api_key' => 'nullable|string',
            'optimistic_etherscan_api_key' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $settings = BlockchainSetting::getInstance();
            $settings->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Explorer API keys saved',
                'data' => $settings
            ]);
        } catch (Exception $e) {
            Log::error('Blockchain setup step 2 error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save API keys',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Step 3: Configure smart contracts
     */
    public function step3_SmartContracts(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'ethereum_contract_address' => 'nullable|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'polygon_contract_address' => 'nullable|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'bsc_contract_address' => 'nullable|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'arbitrum_contract_address' => 'nullable|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'optimism_contract_address' => 'nullable|string|regex:/^0x[a-fA-F0-9]{40}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $settings = BlockchainSetting::getInstance();
            $settings->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Smart contract addresses saved',
                'data' => $settings
            ]);
        } catch (Exception $e) {
            Log::error('Blockchain setup step 3 error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save contract addresses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Step 4: Create or import wallet
     */
    public function step4_CreateWallet(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:create,import',
            'network' => 'required|string|in:ethereum,polygon,bsc,arbitrum,optimism',
            'name' => 'required|string|max:255',
            'private_key' => 'required_if:action,import|string',
            'address' => 'required_if:action,import|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'mnemonic' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $settings = BlockchainSetting::getInstance();
            $network = $request->network;

            if ($request->action === 'create') {
                // Generate new wallet (would need a library to generate)
                // For now, require import
                return response()->json([
                    'success' => false,
                    'message' => 'Wallet generation not yet implemented. Please import an existing wallet.',
                ], 400);
            }

            // Import existing wallet
            $address = $request->address;
            $privateKey = $request->private_key;

            // Validate address matches private key (basic validation)
            // In production, you'd derive address from private key to verify

            // Check if wallet already exists
            $existing = BlockchainWallet::where('network', $network)
                ->where('address', $address)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wallet with this address already exists for this network',
                ], 409);
            }

            // Check if default wallet exists for this network
            $hasDefault = BlockchainWallet::where('network', $network)
                ->where('is_default', true)
                ->exists();

            // Create wallet
            $wallet = BlockchainWallet::create([
                'name' => $request->name,
                'network' => $network,
                'address' => $address,
                'is_active' => true,
                'is_default' => !$hasDefault, // Set as default if no default exists
                'created_by' => $user->id,
            ]);

            // Encrypt and store private key
            $wallet->setPrivateKey($privateKey);
            
            if ($request->mnemonic) {
                $wallet->setMnemonic($request->mnemonic);
            }

            $wallet->save();

            // Try to fetch balance
            try {
                $rpcUrl = $settings->getRpcUrl($network);
                if ($rpcUrl) {
                    $balance = $this->rpcService->getBalance($rpcUrl, $address);
                    if ($balance) {
                        $wallet->balance = $this->rpcService->hexWeiToEther($balance);
                        $wallet->last_synced_at = now();
                        $wallet->save();
                    }
                }
            } catch (Exception $e) {
                Log::warning("Failed to fetch wallet balance: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Wallet imported successfully',
                'data' => [
                    'id' => $wallet->id,
                    'name' => $wallet->name,
                    'network' => $wallet->network,
                    'address' => $wallet->address,
                    'is_default' => $wallet->is_default,
                    'balance' => (float) $wallet->balance,
                ]
            ], 201);

        } catch (Exception $e) {
            Log::error('Blockchain setup step 4 error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create/import wallet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Step 5: Configure webhooks and finalize
     */
    public function step5_CompleteSetup(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'webhooks_enabled' => 'nullable|boolean',
            'webhook_secret' => 'nullable|string',
            'webhook_url' => 'nullable|url',
            'gas_price_multiplier' => 'nullable|numeric|min:1|max:5',
            'default_gas_limit' => 'nullable|integer|min:21000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $settings = BlockchainSetting::getInstance();

            // Verify we have a default wallet for primary network
            $defaultWallet = BlockchainWallet::getDefaultForNetwork($settings->primary_network);
            if (!$defaultWallet) {
                return response()->json([
                    'success' => false,
                    'message' => 'No default wallet found for primary network. Please create a wallet first.',
                ], 400);
            }

            // Update settings
            $updateData = $validator->validated();
            $updateData['is_enabled'] = true;
            $updateData['setup_completed'] = true;
            $updateData['setup_completed_by'] = $user->id;
            $updateData['setup_completed_at'] = now();

            $settings->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Blockchain setup completed successfully!',
                'data' => [
                    'settings' => $settings,
                    'default_wallet' => [
                        'id' => $defaultWallet->id,
                        'name' => $defaultWallet->name,
                        'network' => $defaultWallet->network,
                        'address' => $defaultWallet->address,
                    ],
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Blockchain setup completion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete setup',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test network connection
     */
    public function testConnection(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'network' => 'required|string|in:ethereum,polygon,bsc,arbitrum,optimism',
            'rpc_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $rpcUrl = $request->rpc_url;
            $network = $request->network;

            // Test by getting block number
            $blockNumber = $this->rpcService->getBlockNumber($rpcUrl);

            if ($blockNumber !== null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful',
                    'data' => [
                        'block_number' => $blockNumber,
                        'network' => $network,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Connection failed - could not retrieve block number',
            ], 400);

        } catch (Exception $e) {
            Log::error('Connection test error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
