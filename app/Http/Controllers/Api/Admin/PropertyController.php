<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = Property::with(['images', 'allocations.member.user']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('property_type', $request->type);
        }

        $properties = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $properties->items(),
            'pagination' => [
                'current_page' => $properties->currentPage(),
                'last_page' => $properties->lastPage(),
                'per_page' => $properties->perPage(),
                'total' => $properties->total(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:apartment,house,duplex,bungalow,land,commercial',
            'property_type' => 'sometimes|string',
            'location' => 'required|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'size' => 'nullable|numeric|min:0',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'status' => 'sometimes|string|in:available,allocated,sold,maintenance',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $data = $validator->validated();
            // Normalize property_type only if the column exists
            if (!Schema::hasColumn('properties', 'property_type')) {
                unset($data['property_type']);
            } elseif (!isset($data['property_type'])) {
                $data['property_type'] = $data['type'];
            }

            $features = $data['features'] ?? null;
            unset($data['images']);

            $property = Property::create($data);

            if (is_array($features)) {
                $property->features = $features;
                $property->save();
            }

            if (isset($validator->validated()['images']) && is_array($validator->validated()['images'])) {
                $this->syncPropertyImages($property, $validator->validated()['images']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property created successfully',
                'data' => $property->load(['images', 'allocations.member.user'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create property',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $property = Property::with(['images', 'allocations.member.user'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $property
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $property = Property::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|string|in:apartment,house,duplex,bungalow,land,commercial',
            'property_type' => 'sometimes|string',
            'location' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'size' => 'nullable|numeric|min:0',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'status' => 'sometimes|string|in:available,allocated,sold,maintenance',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $validated = $validator->validated();
            // Use property_type if provided, otherwise use type if provided
            if (Schema::hasColumn('properties', 'property_type')) {
                if (isset($validated['type']) && !isset($validated['property_type'])) {
                    $validated['property_type'] = $validated['type'];
                }
            } else {
                unset($validated['property_type']);
            }

            $images = $validated['images'] ?? null;
            unset($validated['images']);

            $property->update($validated);

            if (is_array($images)) {
                $this->syncPropertyImages($property, $images);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property updated successfully',
                'data' => $property->fresh()->load(['images', 'allocations.member.user'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update property',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $property = Property::findOrFail($id);

        if ($property->allocations()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete property with allocations'
            ], 400);
        }

        $property->delete();

        return response()->json([
            'success' => true,
            'message' => 'Property deleted successfully'
        ]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:10240',
            'property_id' => 'nullable|uuid|exists:properties,id',
            'is_primary' => 'nullable|boolean',
            'alt_text' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $path = $request->file('image')->store('property-images', 'public');
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('public');
            $url = $disk->url($path);

            $propertyImage = null;

            if ($request->filled('property_id')) {
                $property = Property::findOrFail($request->property_id);

                if ($request->boolean('is_primary')) {
                    $property->images()->update(['is_primary' => false]);
                }

                $propertyImage = $property->images()->create([
                    'url' => $url,
                    'is_primary' => $request->boolean('is_primary', !$property->images()->exists()),
                    'alt_text' => $request->input('alt_text'),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'url' => $url,
                'image' => $propertyImage,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    protected function syncPropertyImages(Property $property, array $urls): void
    {
        $property->images()->delete();

        foreach ($urls as $index => $url) {
            if (!is_string($url) || empty($url)) {
                continue;
            }

            $property->images()->create([
                'url' => $url,
                'is_primary' => $index === 0,
            ]);
        }
    }
}

