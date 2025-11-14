<?php

namespace App\Http\Controllers\Api\Properties;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Property;
use App\Models\Tenant\PropertyInterest;
use App\Models\Tenant\PropertyAllocation;
use App\Models\Tenant\PropertyTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PropertyTransferController extends Controller
{
    public function transfer(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $member = $user->member;

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|string|exists:properties,id',
            'transfer_type' => 'required|string|in:sale,gift,external',
            'buyer_name' => 'required|string|max:255',
            'buyer_contact' => 'nullable|string|max:255',
            'buyer_email' => 'nullable|email|max:255',
            'sale_price' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:1000',
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Verify property ownership
            $property = Property::findOrFail($request->property_id);
            $propertyInterest = PropertyInterest::where('property_id', $property->id)
                ->where('member_id', $member->id)
                ->where('status', 'approved')
                ->first();

            if (!$propertyInterest) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have an approved interest in this property',
                ], 403);
            }

            // Check if property is fully paid
            $totalPaid = DB::table('property_payment_transactions')
                ->where('property_id', $property->id)
                ->where('member_id', $member->id)
                ->where('status', 'completed')
                ->sum('amount');

            $propertyPrice = (float) ($property->price ?? 0);
            $progress = $propertyPrice > 0 ? ($totalPaid / $propertyPrice) * 100 : 0;

            if ($progress < 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property must be fully paid before it can be transferred. Current progress: ' . round($progress, 2) . '%',
                ], 400);
            }

            // Calculate transfer fee (2% of property value)
            $currentValue = (float) ($property->current_value ?? $property->price ?? 0);
            $transferFee = $currentValue * 0.02;

            // Store uploaded documents
            $documentPaths = [];
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store('property-transfers', 'public');
                    $documentPaths[] = $path;
                }
            }

            // Create transfer request record
            $transferRequest = PropertyTransfer::create([
                'property_id' => $property->id,
                'member_id' => $member->id,
                'transfer_type' => $request->transfer_type,
                'buyer_name' => $request->buyer_name,
                'buyer_contact' => $request->buyer_contact,
                'buyer_email' => $request->buyer_email,
                'sale_price' => $request->sale_price,
                'transfer_fee' => $transferFee,
                'reason' => $request->reason,
                'documents' => $documentPaths,
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property transfer request submitted successfully. It will be reviewed by the Housing Cooperative.',
                'data' => [
                    'transfer_id' => $transferRequest->id,
                    'transfer_fee' => $transferFee,
                    'estimated_processing_days' => 14,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit transfer request: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getTransferHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $member = $user->member;

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found',
            ], 404);
        }

        $transfers = PropertyTransfer::where('member_id', $member->id)
            ->with(['property'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($transfer) {
                return [
                    'id' => $transfer->id,
                    'property_id' => $transfer->property_id,
                    'property' => $transfer->property ? [
                        'id' => $transfer->property->id,
                        'title' => $transfer->property->title,
                        'location' => $transfer->property->location,
                    ] : null,
                    'transfer_type' => $transfer->transfer_type,
                    'buyer_name' => $transfer->buyer_name,
                    'sale_price' => $transfer->sale_price,
                    'transfer_fee' => $transfer->transfer_fee,
                    'status' => $transfer->status,
                    'created_at' => $transfer->created_at,
                    'updated_at' => $transfer->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $transfers,
        ]);
    }
}

