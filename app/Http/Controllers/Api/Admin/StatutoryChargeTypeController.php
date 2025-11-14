<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\StatutoryCharge;
use App\Models\Tenant\StatutoryChargeType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatutoryChargeTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $types = StatutoryChargeType::orderBy('sort_order')
                ->orderBy('type')
                ->get()
                ->map(function($type) {
                    $chargeCount = StatutoryCharge::where('type', $type->type)->count();
                    $totalAmount = StatutoryCharge::where('type', $type->type)->sum('amount');
                    
                    return [
                        'id' => $type->id,
                        'type' => $type->type,
                        'description' => $type->description,
                        'default_amount' => $type->default_amount ? (float) $type->default_amount : null,
                        'frequency' => $type->frequency,
                        'frequency_display' => $this->formatFrequency($type->frequency),
                        'is_active' => $type->is_active,
                        'sort_order' => $type->sort_order,
                        'count' => $chargeCount,
                        'total_amount' => (float) $totalAmount,
                        'created_at' => $type->created_at?->toIso8601String(),
                        'updated_at' => $type->updated_at?->toIso8601String(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $types
            ]);
        } catch (\Throwable $exception) {
            Log::error('StatutoryChargeTypeController::index() failed', [
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to load charge types at the moment.'
                    : $exception->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:255|unique:statutory_charge_types,type',
            'description' => 'nullable|string',
            'default_amount' => 'nullable|numeric|min:0',
            'frequency' => 'nullable|in:monthly,quarterly,bi_annually,annually',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $type = StatutoryChargeType::create([
                'type' => $request->type,
                'description' => $request->description,
                'default_amount' => $request->filled('default_amount') ? $request->input('default_amount') : null,
                'frequency' => $request->filled('frequency') ? $request->frequency : 'annually',
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => $request->integer('sort_order', 0),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Charge type created successfully',
                'data' => [
                    'id' => $type->id,
                    'type' => $type->type,
                    'description' => $type->description,
                    'default_amount' => $type->default_amount ? (float) $type->default_amount : null,
                    'frequency' => $type->frequency,
                    'frequency_display' => $this->formatFrequency($type->frequency),
                    'is_active' => $type->is_active,
                    'sort_order' => $type->sort_order,
                    'created_at' => $type->created_at?->toIso8601String(),
                    'updated_at' => $type->updated_at?->toIso8601String(),
                ]
            ], 201);
        } catch (\Throwable $exception) {
            Log::error('StatutoryChargeTypeController::store() failed', [
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to create charge type at the moment.'
                    : $exception->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $type = StatutoryChargeType::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'type' => 'sometimes|required|string|max:255|unique:statutory_charge_types,type,' . $id . ',id',
                'description' => 'nullable|string',
                'default_amount' => 'nullable|numeric|min:0',
                'frequency' => 'nullable|in:monthly,quarterly,bi_annually,annually',
                'is_active' => 'nullable|boolean',
                'sort_order' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldType = $type->type;
            
            $type->update([
                'type' => $request->filled('type') ? $request->type : $type->type,
                'description' => $request->filled('description') ? $request->description : $type->description,
                'default_amount' => $request->has('default_amount') ? ($request->filled('default_amount') ? $request->input('default_amount') : null) : $type->default_amount,
                'frequency' => $request->filled('frequency') ? $request->frequency : $type->frequency,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $type->is_active,
                'sort_order' => $request->filled('sort_order') ? $request->integer('sort_order') : $type->sort_order,
            ]);

            // If type name changed, update all related charges
            if ($oldType !== $type->type) {
                $updated = StatutoryCharge::where('type', $oldType)
                    ->update(['type' => $type->type]);
                
                Log::info("Updated {$updated} charges from type '{$oldType}' to '{$type->type}'");
            }

            return response()->json([
                'success' => true,
                'message' => 'Charge type updated successfully',
                'data' => [
                    'id' => $type->id,
                    'type' => $type->type,
                    'description' => $type->description,
                    'default_amount' => $type->default_amount ? (float) $type->default_amount : null,
                    'frequency' => $type->frequency,
                    'frequency_display' => $this->formatFrequency($type->frequency),
                    'is_active' => $type->is_active,
                    'sort_order' => $type->sort_order,
                    'created_at' => $type->created_at?->toIso8601String(),
                    'updated_at' => $type->updated_at?->toIso8601String(),
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Charge type not found'
            ], 404);
        } catch (\Throwable $exception) {
            Log::error('StatutoryChargeTypeController::update() failed', [
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to update charge type at the moment.'
                    : $exception->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $type = StatutoryChargeType::findOrFail($id);
            
            $count = StatutoryCharge::where('type', $type->type)->count();
            
            if ($count > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete charge type with {$count} existing charges. Update or delete charges first."
                ], 400);
            }

            $type->delete();

            return response()->json([
                'success' => true,
                'message' => 'Charge type deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Charge type not found'
            ], 404);
        } catch (\Throwable $exception) {
            Log::error('StatutoryChargeTypeController::destroy() failed', [
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to delete charge type at the moment.'
                    : $exception->getMessage(),
            ], 500);
        }
    }

    private function formatFrequency(string $frequency): string
    {
        return match($frequency) {
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'bi_annually' => 'Bi-Annually',
            'annually' => 'Annual',
            default => ucfirst($frequency),
        };
    }
}

