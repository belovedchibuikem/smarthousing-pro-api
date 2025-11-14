<?php

namespace App\Http\Controllers\Api\Properties;

use App\Http\Controllers\Controller;
use App\Http\Requests\Properties\PropertyRequest;
use App\Http\Resources\Properties\PropertyResource;
use App\Models\Tenant\Property;
use App\Models\Tenant\PropertyInterest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Property::with(['images', 'allocations']);

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        $properties = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'properties' => PropertyResource::collection($properties),
            'pagination' => [
                'current_page' => $properties->currentPage(),
                'last_page' => $properties->lastPage(),
                'per_page' => $properties->perPage(),
                'total' => $properties->total(),
            ]
        ]);
    }

    public function store(PropertyRequest $request): JsonResponse
    {
        $property = Property::create($request->validated());

        // Handle image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('properties', 'public');
                $property->images()->create([
                    'url' => $path,
                    'is_primary' => false,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Property created successfully',
            'property' => new PropertyResource($property->load('images'))
        ], 201);
    }

    public function show(Request $request, Property $property): JsonResponse
    {
        $property->load(['images', 'allocations.member']);

        $user = $request->user();
        $interest = null;

        if ($user && $user->member) {
            $interest = PropertyInterest::where('property_id', $property->id)
                ->where('member_id', $user->member->id)
                ->orderByDesc('created_at')
                ->first();
        }

        if ($interest) {
            $property->setAttribute('interest_status', $interest->status);
            $property->setAttribute('interest_id', $interest->id);
            $property->setAttribute('interest_type', $interest->interest_type);
        }
        
        return response()->json([
            'property' => new PropertyResource($property),
            'interest' => $interest,
        ]);
    }

    public function update(PropertyRequest $request, Property $property): JsonResponse
    {
        $property->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Property updated successfully',
            'property' => new PropertyResource($property->load('images'))
        ]);
    }

    public function destroy(Property $property): JsonResponse
    {
        $property->delete();

        return response()->json([
            'success' => true,
            'message' => 'Property deleted successfully'
        ]);
    }
}
