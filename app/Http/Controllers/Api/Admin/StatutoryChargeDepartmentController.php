<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\StatutoryCharge;
use App\Models\Tenant\StatutoryChargeDepartment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class StatutoryChargeDepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Get all departments with their statistics
        $departments = StatutoryChargeDepartment::withCount(['charges as charge_count'])
            ->orderBy('name')
            ->get()
            ->map(function($department) {
                $totalAllocated = (float) $department->charges()->sum('amount');
                $totalCollected = (float) $department->charges()->where('status', 'paid')->sum('amount');
                $collectionRate = $totalAllocated > 0 
                    ? (($totalCollected / $totalAllocated) * 100) 
                    : 0;

                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'description' => $department->description,
                    'is_active' => $department->is_active,
                    'charge_count' => $department->charge_count ?? 0,
                    'total_allocated' => $totalAllocated,
                    'total_collected' => $totalCollected,
                    'collection_rate' => $collectionRate,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $departments
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if a department with this name already exists
            $existingDepartment = StatutoryChargeDepartment::where('name', $request->name)->first();

            if ($existingDepartment) {
                return response()->json([
                    'success' => false,
                    'message' => 'A department with this name already exists',
                ], 409); // Conflict status code
            }

            // Create the department
            $department = StatutoryChargeDepartment::create([
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => true,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Department created successfully',
                'data' => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'description' => $department->description,
                    'is_active' => $department->is_active,
                    'charge_count' => 0,
                    'total_allocated' => 0,
                    'total_collected' => 0,
                    'collection_rate' => 0,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        // Update department name by updating all charges of that type
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find the department by ID
            $department = StatutoryChargeDepartment::findOrFail($id);

            // Check if the new name is already in use by a different department
            if ($department->name !== $request->name) {
                $existingDepartment = StatutoryChargeDepartment::where('name', $request->name)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingDepartment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A department with this name already exists',
                    ], 409);
                }
            }

            // Update the department
            $department->update([
                'name' => $request->name,
            ]);

            // Refresh to get updated relationships
            $department->refresh();

            // Calculate statistics
            $chargeCount = $department->charges()->count();
            $totalAllocated = (float) $department->charges()->sum('amount');
            $totalCollected = (float) $department->charges()->where('status', 'paid')->sum('amount');
            $collectionRate = $totalAllocated > 0 
                ? (($totalCollected / $totalAllocated) * 100) 
                : 0;

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully',
                'data' => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'description' => $department->description,
                    'is_active' => $department->is_active,
                    'charge_count' => $chargeCount,
                    'total_allocated' => $totalAllocated,
                    'total_collected' => $totalCollected,
                    'collection_rate' => $collectionRate,
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $department = StatutoryChargeDepartment::findOrFail($id);
            
            $count = $department->charges()->count();
            
            if ($count > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete department with {$count} existing charge(s). Update or delete charges first."
                ], 400);
            }

            $department->delete();

            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

