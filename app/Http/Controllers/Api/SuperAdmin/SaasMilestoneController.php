<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\SaasMilestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SaasMilestoneController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SaasMilestone::query();

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $milestones = $query->ordered()->get();

            return response()->json([
                'success' => true,
                'milestones' => $milestones->map(function ($milestone) {
                    return [
                        'id' => $milestone->id,
                        'year' => $milestone->year,
                        'event' => $milestone->event,
                        'icon' => $milestone->icon,
                        'order_index' => $milestone->order_index,
                        'is_active' => $milestone->is_active,
                        'created_at' => $milestone->created_at,
                        'updated_at' => $milestone->updated_at,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch milestones', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch milestones',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'year' => 'required|string|max:10',
                'event' => 'required|string',
                'icon' => 'nullable|string|max:100',
                'order_index' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);

            $milestone = SaasMilestone::create(array_merge($validated, [
                'order_index' => $validated['order_index'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Milestone created successfully',
                'milestone' => $milestone,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create milestone', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create milestone',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $milestone = SaasMilestone::findOrFail($id);
            $validated = $request->validate([
                'year' => 'sometimes|required|string|max:10',
                'event' => 'sometimes|required|string',
                'icon' => 'nullable|string|max:100',
                'order_index' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);

            $milestone->update($validated);
            return response()->json(['success' => true, 'message' => 'Milestone updated successfully', 'milestone' => $milestone]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Milestone not found'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update milestone', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update milestone',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $milestone = SaasMilestone::findOrFail($id);
            $milestone->delete();
            return response()->json(['success' => true, 'message' => 'Milestone deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Milestone not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete milestone', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete milestone',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
