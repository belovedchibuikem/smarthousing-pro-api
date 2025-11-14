<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\BlockchainPropertyRecord;
use App\Models\Tenant\Property;
use App\Models\Tenant\PropertyAllocation;
use App\Models\Tenant\BlockchainSetting;
use App\Services\BlockchainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BlockchainPropertyController extends Controller
{
    protected BlockchainService $blockchainService;

    public function __construct(BlockchainService $blockchainService)
    {
        $this->blockchainService = $blockchainService;
    }

    /**
     * Get all blockchain property records
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = BlockchainPropertyRecord::with([
            'property',
            'registrant:id,first_name,last_name,email',
            'verifier:id,first_name,last_name,email'
        ]);

        // Search by property title, hash, or transaction hash
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('blockchain_hash', 'like', "%{$search}%")
                  ->orWhere('transaction_hash', 'like', "%{$search}%")
                  ->orWhereHas('property', function($propertyQuery) use ($search) {
                      $propertyQuery->where('title', 'like', "%{$search}%")
                                   ->orWhere('location', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by network
        if ($request->has('network')) {
            $query->where('network', $request->network);
        }

        $records = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ]
        ]);
    }

    /**
     * Get blockchain statistics
     */
    public function stats(): JsonResponse
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $totalProperties = BlockchainPropertyRecord::count();
        $confirmedProperties = BlockchainPropertyRecord::where('status', 'confirmed')->count();
        $pendingProperties = BlockchainPropertyRecord::where('status', 'pending')->count();
        $failedProperties = BlockchainPropertyRecord::where('status', 'failed')->count();
        
        // Count unique owners (from ownership_data)
        $allRecords = BlockchainPropertyRecord::where('status', 'confirmed')
            ->whereNotNull('ownership_data')
            ->get();
        
        $verifiedOwners = collect();
        foreach ($allRecords as $record) {
            if (is_array($record->ownership_data)) {
                foreach ($record->ownership_data as $owner) {
                    if (isset($owner['member_id'])) {
                        $verifiedOwners->push($owner['member_id']);
                    }
                }
            }
        }
        $uniqueOwners = $verifiedOwners->unique()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_properties' => $totalProperties,
                'confirmed_properties' => $confirmedProperties,
                'pending_properties' => $pendingProperties,
                'failed_properties' => $failedProperties,
                'verified_owners' => $uniqueOwners,
                'network_status' => 'active', // Could check actual blockchain connection
            ]
        ]);
    }

    /**
     * Get single blockchain property record
     */
    public function show(string $id): JsonResponse
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $record = BlockchainPropertyRecord::with([
            'property',
            'property.images',
            'property.allocations.member.user',
            'registrant:id,first_name,last_name,email',
            'verifier:id,first_name,last_name,email'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $record
        ]);
    }

    /**
     * Register property on blockchain
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check if blockchain is set up
        $settings = BlockchainSetting::getInstance();
        if (!$settings->setup_completed || !$settings->is_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Blockchain is not set up. Please complete the blockchain setup wizard first.',
                'setup_required' => true,
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|uuid|exists:properties,id',
            'network' => 'nullable|string|in:ethereum,polygon,bsc,arbitrum,optimism',
            'ownership_data' => 'nullable|array',
            'ownership_data.*.member_id' => 'required_with:ownership_data|uuid|exists:members,id',
            'ownership_data.*.wallet_address' => 'nullable|string',
            'ownership_data.*.ownership_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $property = Property::with(['allocations.member.user'])->findOrFail($request->property_id);

            // Check if property already has a confirmed blockchain record
            $existingRecord = BlockchainPropertyRecord::where('property_id', $property->id)
                ->where('status', 'confirmed')
                ->first();

            if ($existingRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property already has a confirmed blockchain record',
                    'data' => $existingRecord
                ], 409);
            }

            // Prepare ownership data
            $ownershipData = [];
            if ($request->has('ownership_data') && !empty($request->ownership_data)) {
                $ownershipData = $request->ownership_data;
            } else {
                // Auto-populate from property allocations if not provided
                $allocations = PropertyAllocation::where('property_id', $property->id)
                    ->where('status', 'approved')
                    ->with('member.user')
                    ->get();

                foreach ($allocations as $allocation) {
                    $ownershipData[] = [
                        'member_id' => $allocation->member_id,
                        'member_number' => $allocation->member->member_number ?? null,
                        'name' => $allocation->member->user 
                            ? ($allocation->member->user->first_name . ' ' . $allocation->member->user->last_name)
                            : 'Unknown',
                        'wallet_address' => null, // Can be added later
                        'ownership_percentage' => null, // Can be calculated if needed
                        'allocation_date' => $allocation->allocation_date?->toDateString(),
                    ];
                }
            }

            if (empty($ownershipData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property must have at least one owner to register on blockchain'
                ], 422);
            }

            // Generate blockchain hash
            $blockchainHash = $this->blockchainService->generatePropertyHash($property, $ownershipData);

            // Generate property snapshot
            $propertySnapshot = $this->blockchainService->generatePropertySnapshot($property);

            // Create blockchain record
            $blockchainRecord = BlockchainPropertyRecord::create([
                'property_id' => $property->id,
                'blockchain_hash' => $blockchainHash,
                'status' => 'pending',
                'property_data' => $propertySnapshot,
                'ownership_data' => $ownershipData,
                'network' => $request->network ?? 'ethereum',
                'registered_at' => now(),
                'registered_by' => $user->id,
            ]);

            // Attempt blockchain registration
            $network = $request->network ?? 'ethereum';
            $registrationResult = $this->blockchainService->registerPropertyOnBlockchain(
                $blockchainRecord,
                $network
            );

            if ($registrationResult['success']) {
                $blockchainRecord->update([
                    'transaction_hash' => $registrationResult['transaction_hash'],
                    'gas_fee' => $registrationResult['gas_fee'] ?? null,
                    'gas_price' => $registrationResult['gas_price'] ?? null,
                    'network' => $registrationResult['network'],
                ]);
            } else {
                $blockchainRecord->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'failure_reason' => $registrationResult['error'] ?? 'Registration failed',
                ]);

                DB::commit();

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to register property on blockchain',
                    'error' => $registrationResult['error'] ?? 'Unknown error',
                    'data' => $blockchainRecord
                ], 500);
            }

            DB::commit();

            $blockchainRecord->load([
                'property',
                'registrant:id,first_name,last_name,email'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Property registration initiated on blockchain',
                'data' => $blockchainRecord
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Blockchain registration error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to register property on blockchain',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update blockchain property record (for verification, notes, etc.)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|string|in:pending,confirmed,failed,rejected',
            'verification_notes' => 'nullable|string',
            'transaction_hash' => 'nullable|string',
            'block_number' => 'nullable|integer',
            'failure_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $record = BlockchainPropertyRecord::findOrFail($id);
            
            $updateData = $validator->validated();
            
            // If status is being changed to confirmed, set confirmed_at and verified_by
            if (isset($updateData['status']) && $updateData['status'] === 'confirmed' && $record->status !== 'confirmed') {
                $updateData['confirmed_at'] = now();
                $updateData['verified_by'] = $user->id;
            }

            // If status is being changed to failed, set failed_at
            if (isset($updateData['status']) && $updateData['status'] === 'failed' && $record->status !== 'failed') {
                $updateData['failed_at'] = now();
            }

            $record->update($updateData);

            $record->load([
                'property',
                'registrant:id,first_name,last_name,email',
                'verifier:id,first_name,last_name,email'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Blockchain record updated successfully',
                'data' => $record
            ]);

        } catch (\Exception $e) {
            Log::error('Blockchain record update error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update blockchain record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify blockchain transaction
     */
    public function verifyTransaction(string $id): JsonResponse
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $record = BlockchainPropertyRecord::findOrFail($id);

            if (!$record->transaction_hash) {
                return response()->json([
                    'success' => false,
                    'message' => 'No transaction hash available for verification'
                ], 400);
            }

            // Verify transaction on blockchain
            $verificationResult = $this->blockchainService->verifyTransaction(
                $record->transaction_hash,
                $record->network
            );

            if ($verificationResult['valid'] && $verificationResult['confirmed']) {
                $record->update([
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                    'verified_by' => $user->id,
                    'block_number' => $verificationResult['block_number'] ?? $record->block_number,
                    'verification_notes' => 'Transaction verified on blockchain',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction verified successfully',
                    'data' => [
                        'record' => $record->fresh(),
                        'verification' => $verificationResult
                    ]
                ]);
            } else {
                $record->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'failure_reason' => $verificationResult['error'] ?? 'Transaction verification failed',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Transaction verification failed',
                    'data' => [
                        'record' => $record->fresh(),
                        'verification' => $verificationResult
                    ]
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Transaction verification error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete blockchain record (soft delete or hard delete depending on status)
     */
    public function destroy(string $id): JsonResponse
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $record = BlockchainPropertyRecord::findOrFail($id);

            // Don't allow deletion of confirmed records
            if ($record->status === 'confirmed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete confirmed blockchain records'
                ], 400);
            }

            $record->delete();

            return response()->json([
                'success' => true,
                'message' => 'Blockchain record deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Blockchain record deletion error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete blockchain record',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

