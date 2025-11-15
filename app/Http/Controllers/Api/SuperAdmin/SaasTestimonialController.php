<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\SaasTestimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SaasTestimonialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SaasTestimonial::query();

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('is_featured')) {
                $query->where('is_featured', $request->boolean('is_featured'));
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%")
                      ->orWhere('role', 'like', "%{$search}%");
                });
            }

            $testimonials = $query->ordered()->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'testimonials' => $testimonials->items(),
                'pagination' => [
                    'current_page' => $testimonials->currentPage(),
                    'last_page' => $testimonials->lastPage(),
                    'per_page' => $testimonials->perPage(),
                    'total' => $testimonials->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch testimonials', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch testimonials',
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
                'content' => 'required|string',
                'rating' => 'nullable|integer|min:1|max:5',
                'avatar_url' => 'nullable|string|max:500',
                'company' => 'nullable|string|max:255',
                'order_index' => 'nullable|integer|min:0',
                'is_featured' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
            ]);

            $testimonial = SaasTestimonial::create(array_merge($validated, [
                'rating' => $validated['rating'] ?? 5,
                'order_index' => $validated['order_index'] ?? 0,
                'is_featured' => $validated['is_featured'] ?? false,
                'is_active' => $validated['is_active'] ?? true,
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Testimonial created successfully',
                'testimonial' => $testimonial,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create testimonial', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create testimonial',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $testimonial = SaasTestimonial::findOrFail($id);
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'role' => 'sometimes|required|string|max:255',
                'content' => 'sometimes|required|string',
                'rating' => 'nullable|integer|min:1|max:5',
                'avatar_url' => 'nullable|string|max:500',
                'company' => 'nullable|string|max:255',
                'order_index' => 'nullable|integer|min:0',
                'is_featured' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
            ]);

            $testimonial->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Testimonial updated successfully',
                'testimonial' => $testimonial,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Testimonial not found'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update testimonial', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update testimonial',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $testimonial = SaasTestimonial::findOrFail($id);
            $testimonial->delete();
            return response()->json(['success' => true, 'message' => 'Testimonial deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Testimonial not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete testimonial', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete testimonial',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
