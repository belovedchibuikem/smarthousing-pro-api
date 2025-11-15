<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\SaasValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SaasValueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SaasValue::query();

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $values = $query->ordered()->get();

            return response()->json([
                'success' => true,
                'values' => $values->map(function ($value) {
                    return [
                        'id' => $value->id,
                        'title' => $value->title,
                        'description' => $value->description,
                        'icon' => $value->icon,
                        'order_index' => $value->order_index,
                        'is_active' => $value->is_active,
                        'created_at' => $value->created_at,
                        'updated_at' => $value->updated_at,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch values', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch values',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'icon' => 'nullable|string|max:100',
                'order_index' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);

            $value = SaasValue::create(array_merge($validated, [
                'order_index' => $validated['order_index'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Value created successfully',
                'value' => $value,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create value', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create value',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $value = SaasValue::findOrFail($id);
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string',
                'icon' => 'nullable|string|max:100',
                'order_index' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);

            $value->update($validated);
            return response()->json(['success' => true, 'message' => 'Value updated successfully', 'value' => $value]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Value not found'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update value', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update value',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $value = SaasValue::findOrFail($id);
            $value->delete();
            return response()->json(['success' => true, 'message' => 'Value deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Value not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete value', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete value',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
