<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\SaasPageSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SaasPageController extends Controller
{
    /**
     * List all pages with their sections
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SaasPageSection::query();

            // Filter by page type
            if ($request->has('page_type')) {
                $query->where('page_type', $request->page_type);
            }

            // Filter by section type
            if ($request->has('section_type')) {
                $query->where('section_type', $request->section_type);
            }

            // Filter by published status
            if ($request->has('is_published')) {
                $query->where('is_published', $request->boolean('is_published'));
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $sections = $query->ordered()->get();

            // Group by page type
            $grouped = $sections->groupBy('page_type')->map(function ($sections) {
                return $sections->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'section_type' => $section->section_type,
                        'section_key' => $section->section_key,
                        'title' => $section->title,
                        'subtitle' => $section->subtitle,
                        'content' => $section->content,
                        'media' => $section->media,
                        'order_index' => $section->order_index,
                        'is_active' => $section->is_active,
                        'is_published' => $section->is_published,
                        'metadata' => $section->metadata,
                        'created_at' => $section->created_at,
                        'updated_at' => $section->updated_at,
                    ];
                })->values();
            });

            return response()->json([
                'success' => true,
                'pages' => $grouped,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch SaaS pages', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pages',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Get sections for a specific page type
     */
    public function show(string $pageType): JsonResponse
    {
        try {
            $sections = SaasPageSection::forPage($pageType)
                ->ordered()
                ->get();

            return response()->json([
                'success' => true,
                'page_type' => $pageType,
                'sections' => $sections->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'section_type' => $section->section_type,
                        'section_key' => $section->section_key,
                        'title' => $section->title,
                        'subtitle' => $section->subtitle,
                        'content' => $section->content,
                        'media' => $section->media,
                        'order_index' => $section->order_index,
                        'is_active' => $section->is_active,
                        'is_published' => $section->is_published,
                        'metadata' => $section->metadata,
                        'created_at' => $section->created_at,
                        'updated_at' => $section->updated_at,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch page sections', [
                'page_type' => $pageType,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch page sections',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Create or update a page section
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page_type' => 'required|string|in:home,community,about,header',
                'section_type' => 'required|string',
                'section_key' => 'required|string|max:100',
                'title' => 'nullable|string|max:255',
                'subtitle' => 'nullable|string',
                'content' => 'nullable|array',
                'media' => 'nullable|array',
                'order_index' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
                'is_published' => 'nullable|boolean',
                'metadata' => 'nullable|array',
            ]);

            $section = SaasPageSection::updateOrCreate(
                [
                    'page_type' => $validated['page_type'],
                    'section_key' => $validated['section_key'],
                ],
                array_merge($validated, [
                    'content' => $validated['content'] ?? [],
                    'media' => $validated['media'] ?? [],
                    'metadata' => $validated['metadata'] ?? [],
                    'order_index' => $validated['order_index'] ?? 0,
                    'is_active' => $validated['is_active'] ?? true,
                    'is_published' => $validated['is_published'] ?? false,
                ])
            );

            return response()->json([
                'success' => true,
                'message' => 'Section saved successfully',
                'section' => [
                    'id' => $section->id,
                    'page_type' => $section->page_type,
                    'section_type' => $section->section_type,
                    'section_key' => $section->section_key,
                    'title' => $section->title,
                    'subtitle' => $section->subtitle,
                    'content' => $section->content,
                    'media' => $section->media,
                    'order_index' => $section->order_index,
                    'is_active' => $section->is_active,
                    'is_published' => $section->is_published,
                    'metadata' => $section->metadata,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to save page section', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save section',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Update a specific section
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $section = SaasPageSection::findOrFail($id);

            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'subtitle' => 'nullable|string',
                'content' => 'nullable|array',
                'media' => 'nullable|array',
                'order_index' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
                'is_published' => 'nullable|boolean',
                'metadata' => 'nullable|array',
            ]);

            $section->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Section updated successfully',
                'section' => [
                    'id' => $section->id,
                    'page_type' => $section->page_type,
                    'section_type' => $section->section_type,
                    'section_key' => $section->section_key,
                    'title' => $section->title,
                    'subtitle' => $section->subtitle,
                    'content' => $section->content,
                    'media' => $section->media,
                    'order_index' => $section->order_index,
                    'is_active' => $section->is_active,
                    'is_published' => $section->is_published,
                    'metadata' => $section->metadata,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update page section', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update section',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Delete a section
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $section = SaasPageSection::findOrFail($id);
            $section->delete();

            return response()->json([
                'success' => true,
                'message' => 'Section deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete page section', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete section',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Reorder sections
     */
    public function reorder(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'sections' => 'required|array',
                'sections.*.id' => 'required|uuid|exists:saas_page_sections,id',
                'sections.*.order_index' => 'required|integer|min:0',
            ]);

            foreach ($validated['sections'] as $item) {
                SaasPageSection::where('id', $item['id'])
                    ->update(['order_index' => $item['order_index']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sections reordered successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to reorder sections', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder sections',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Toggle publish status
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'is_published' => 'required|boolean',
            ]);

            $section = SaasPageSection::findOrFail($id);
            $section->update(['is_published' => $validated['is_published']]);

            return response()->json([
                'success' => true,
                'message' => $validated['is_published'] ? 'Section published' : 'Section unpublished',
                'section' => [
                    'id' => $section->id,
                    'is_published' => $section->is_published,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to toggle publish status', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update publish status',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
