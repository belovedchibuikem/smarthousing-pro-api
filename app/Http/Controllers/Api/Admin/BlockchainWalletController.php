<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\BlockchainWallet;
use App\Models\Tenant\BlockchainSetting;
use App\Services\Web3RpcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;

class BlockchainWalletController extends Controller
{
    protected Web3RpcService $rpcService;

    public function __construct(Web3RpcService $rpcService)
    {
        $this->rpcService = $rpcService;
    }

    /**
     * List all wallets
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = BlockchainWallet::query();

        if ($request->has('network')) {
            $query->where('network', $request->network);
        }

        $wallets = $query->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($wallet) {
                return [
                    'id' => $wallet->id,
                    'name' => $wallet->name,
                    'network' => $wallet->network,
                    'address' => $wallet->address,
                    'is_active' => $wallet->is_active,
                    'is_default' => $wallet->is_default,
                    'balance' => (float) $wallet->balance,
                    'last_synced_at' => $wallet->last_synced_at?->toIso8601String(),
                    'notes' => $wallet->notes,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $wallets
        ]);
    }

    /**
     * Create/Import wallet
     */
    public function store(Request $request): JsonResponse
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
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $network = $request->network;
            $address = $request->address;

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
                'is_default' => !$hasDefault,
                'notes' => $request->notes,
                'created_by' => $user->id,
            ]);

            // Encrypt and store private key
            $wallet->setPrivateKey($request->private_key);
            
            if ($request->mnemonic) {
                $wallet->setMnemonic($request->mnemonic);
            }

            $wallet->save();

            // Sync balance
            $this->syncWalletBalance($wallet);

            return response()->json([
                'success' => true,
                'message' => 'Wallet created successfully',
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
            Log::error('Wallet creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create wallet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single wallet
     */
    public function show(string $id): JsonResponse
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $wallet = BlockchainWallet::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $wallet->id,
                'name' => $wallet->name,
                'network' => $wallet->network,
                'address' => $wallet->address,
                'is_active' => $wallet->is_active,
                'is_default' => $wallet->is_default,
                'balance' => (float) $wallet->balance,
                'last_synced_at' => $wallet->last_synced_at?->toIso8601String(),
                'notes' => $wallet->notes,
            ]
        ]);
    }

    /**
     * Update wallet
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $wallet = BlockchainWallet::findOrFail($id);
            $wallet->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Wallet updated successfully',
                'data' => $wallet->fresh()
            ]);

        } catch (Exception $e) {
            Log::error('Wallet update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update wallet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete wallet
     */
    public function destroy(string $id): JsonResponse
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $wallet = BlockchainWallet::findOrFail($id);

            if ($wallet->is_default) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete default wallet. Set another wallet as default first.',
                ], 400);
            }

            $wallet->delete();

            return response()->json([
                'success' => true,
                'message' => 'Wallet deleted successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Wallet deletion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete wallet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set wallet as default
     */
    public function setDefault(string $id): JsonResponse
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $wallet = BlockchainWallet::findOrFail($id);

            // Remove default from other wallets in same network
            BlockchainWallet::where('network', $wallet->network)
                ->where('id', '!=', $wallet->id)
                ->update(['is_default' => false]);

            // Set this wallet as default
            $wallet->update(['is_default' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Wallet set as default',
                'data' => $wallet->fresh()
            ]);

        } catch (Exception $e) {
            Log::error('Set default wallet error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to set default wallet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync wallet balance
     */
    public function syncBalance(string $id): JsonResponse
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $wallet = BlockchainWallet::findOrFail($id);
            
            $this->syncWalletBalance($wallet);

            return response()->json([
                'success' => true,
                'message' => 'Balance synced successfully',
                'data' => [
                    'balance' => (float) $wallet->balance,
                    'last_synced_at' => $wallet->last_synced_at?->toIso8601String(),
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Balance sync error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync wallet balance from blockchain
     */
    protected function syncWalletBalance(BlockchainWallet $wallet): void
    {
        try {
            $settings = BlockchainSetting::getInstance();
            $rpcUrl = $settings->getRpcUrl($wallet->network);
            
            if ($rpcUrl) {
                $balance = $this->rpcService->getBalance($rpcUrl, $wallet->address);
                if ($balance) {
                    $wallet->balance = $this->rpcService->hexWeiToEther($balance);
                    $wallet->last_synced_at = now();
                    $wallet->save();
                }
            }
        } catch (Exception $e) {
            Log::warning("Failed to sync balance for wallet {$wallet->id}: " . $e->getMessage());
        }
    }
}

