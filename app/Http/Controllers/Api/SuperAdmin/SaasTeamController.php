<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\SaasTeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SaasTeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SaasTeamMember::query();

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('role', 'like', "%{$search}%");
                });
            }

            $members = $query->ordered()->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'team_members' => $members->items(),
                'pagination' => [
                    'current_page' => $members->currentPage(),
                    'last_page' => $members->lastPage(),
                    'per_page' => $members->perPage(),
                    'total' => $members->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch team members', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch team members',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'role' => 'required|string|max:255',
                'bio' => 'nullable|string',
                'avatar_url' => 'nullable|string|max:500',
                'email' => 'nullable|email|max:255',
                'linkedin_url' => 'nullable|url|max:500',
                'twitter_url' => 'nullable|url|max:500',
                'order_index' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);

            $member = SaasTeamMember::create(array_merge($validated, [
                'order_index' => $validated['order_index'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Team member created successfully',
                'team_member' => $member,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create team member', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create team member',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $member = SaasTeamMember::findOrFail($id);
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'role' => 'sometimes|required|string|max:255',
                'bio' => 'nullable|string',
                'avatar_url' => 'nullable|string|max:500',
                'email' => 'nullable|email|max:255',
                'linkedin_url' => 'nullable|url|max:500',
                'twitter_url' => 'nullable|url|max:500',
                'order_index' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);

            $member->update($validated);
            return response()->json(['success' => true, 'message' => 'Team member updated successfully', 'team_member' => $member]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Team member not found'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update team member', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update team member',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $member = SaasTeamMember::findOrFail($id);
            $member->delete();
            return response()->json(['success' => true, 'message' => 'Team member deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Team member not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete team member', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete team member',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
