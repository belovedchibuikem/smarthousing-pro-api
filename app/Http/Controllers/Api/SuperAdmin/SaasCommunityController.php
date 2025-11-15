<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\SaasCommunityDiscussion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SaasCommunityController extends Controller
{
    /**
     * List all discussions
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SaasCommunityDiscussion::query();

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by featured
            if ($request->has('is_featured')) {
                $query->where('is_featured', $request->boolean('is_featured'));
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('question', 'like', "%{$search}%")
                      ->orWhere('author_name', 'like', "%{$search}%");
                });
            }

            $discussions = $query->ordered()->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'discussions' => $discussions->map(function ($discussion) {
                    return [
                        'id' => $discussion->id,
                        'question' => $discussion->question,
                        'author_name' => $discussion->author_name,
                        'author_role' => $discussion->author_role,
                        'author_avatar_url' => $discussion->author_avatar_url,
                        'responses_count' => $discussion->responses_count,
                        'likes_count' => $discussion->likes_count,
                        'views_count' => $discussion->views_count,
                        'tags' => $discussion->tags,
                        'top_answer' => $discussion->top_answer,
                        'other_answers' => $discussion->other_answers,
                        'order_index' => $discussion->order_index,
                        'is_featured' => $discussion->is_featured,
                        'is_active' => $discussion->is_active,
                        'created_at' => $discussion->created_at,
                        'updated_at' => $discussion->updated_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $discussions->currentPage(),
                    'last_page' => $discussions->lastPage(),
                    'per_page' => $discussions->perPage(),
                    'total' => $discussions->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch community discussions', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch discussions',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Create a new discussion
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'question' => 'required|string',
                'author_name' => 'required|string|max:255',
                'author_role' => 'nullable|string|max:255',
                'author_avatar_url' => 'nullable|string|max:500',
                'responses_count' => 'nullable|integer|min:0',
                'likes_count' => 'nullable|integer|min:0',
                'views_count' => 'nullable|integer|min:0',
                'tags' => 'nullable|array',
                'top_answer' => 'nullable|array',
                'other_answers' => 'nullable|array',
                'order_index' => 'nullable|integer|min:0',
                'is_featured' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
            ]);

            $discussion = SaasCommunityDiscussion::create(array_merge($validated, [
                'tags' => $validated['tags'] ?? [],
                'other_answers' => $validated['other_answers'] ?? [],
                'responses_count' => $validated['responses_count'] ?? 0,
                'likes_count' => $validated['likes_count'] ?? 0,
                'views_count' => $validated['views_count'] ?? 0,
                'order_index' => $validated['order_index'] ?? 0,
                'is_featured' => $validated['is_featured'] ?? false,
                'is_active' => $validated['is_active'] ?? true,
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Discussion created successfully',
                'discussion' => $discussion,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create discussion', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create discussion',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Update a discussion
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $discussion = SaasCommunityDiscussion::findOrFail($id);

            $validated = $request->validate([
                'question' => 'sometimes|required|string',
                'author_name' => 'sometimes|required|string|max:255',
                'author_role' => 'nullable|string|max:255',
                'author_avatar_url' => 'nullable|string|max:500',
                'responses_count' => 'nullable|integer|min:0',
                'likes_count' => 'nullable|integer|min:0',
                'views_count' => 'nullable|integer|min:0',
                'tags' => 'nullable|array',
                'top_answer' => 'nullable|array',
                'other_answers' => 'nullable|array',
                'order_index' => 'nullable|integer|min:0',
                'is_featured' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
            ]);

            $discussion->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Discussion updated successfully',
                'discussion' => $discussion,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Discussion not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update discussion', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update discussion',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Delete a discussion
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $discussion = SaasCommunityDiscussion::findOrFail($id);
            $discussion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Discussion deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Discussion not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete discussion', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete discussion',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Reorder discussions
     */
    public function reorder(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'discussions' => 'required|array',
                'discussions.*.id' => 'required|uuid|exists:saas_community_discussions,id',
                'discussions.*.order_index' => 'required|integer|min:0',
            ]);

            foreach ($validated['discussions'] as $item) {
                SaasCommunityDiscussion::where('id', $item['id'])
                    ->update(['order_index' => $item['order_index']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Discussions reordered successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to reorder discussions', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder discussions',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
