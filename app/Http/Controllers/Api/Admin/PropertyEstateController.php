<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Property;
use App\Models\Tenant\PropertyAllocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropertyEstateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
       

        
        // Group properties by location/estate name
        try {
            $estates = Property::select(
                            'location', 
                            DB::raw('COALESCE(city, "") as city'), 
                            DB::raw('COALESCE(state, "") as state'),
                            DB::raw('COUNT(*) as total_properties'),
                            DB::raw('COUNT(CASE WHEN status = "available" THEN 1 END) as available_properties'),
                            DB::raw('COUNT(CASE WHEN status = "allocated" OR status = "sold" THEN 1 END) as allocated_properties')
                        )
                        ->whereNotNull('location')
                        ->groupBy('location', 'city', 'state')
                        ->orderBy('location')
                        ->get()
                        ->map(function($item) {
                            $totalPlots = (int) ($item->total_properties ?? 0);
                            $allocatedPlots = (int) ($item->allocated_properties ?? 0);
                            $availablePlots = (int) ($item->available_properties ?? 0);
                            
                            $city = $item->city ?? '';
                            $state = $item->state ?? '';
                            return [
                                'id' => md5(($item->location ?? '') . $city . $state),
                                'name' => $item->location ?? 'Unnamed Estate',
                                'location' => trim($city . ', ' . $state, ', ') ?: 'Unknown',
                                'city' => $city,
                                'state' => $state,
                                'total_properties' => $totalPlots,
                                'allocated_properties' => $allocatedPlots,
                                'available_properties' => $availablePlots,
                                'completion_rate' => $totalPlots > 0 ? round(($allocatedPlots / $totalPlots) * 100, 2) : 0,
                            ];
                        });
        } catch (\Exception $e) {
            Log::error('Error fetching estates: ' . $e->getMessage());
            $estates = collect([]); // Return empty collection
        }

            

           

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $estates = $estates->filter(function($estate) use ($search) {
                return stripos($estate['name'], $search) !== false 
                    || stripos($estate['location'], $search) !== false;
            })->values();
        }

        return response()->json([
            'success' => true,
            'data' => $estates->values()
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Estates are created through properties, so we create a property with estate info
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create a placeholder property representing the estate
        try {
            $property = Property::create([
                'title' => $request->name . ' Estate',
                'description' => $request->description ?? 'Estate property',
                'type' => 'estate',
                'location' => $request->name,
                'city' => $request->city,
                'state' => $request->state,
                'status' => 'under_development',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estate created successfully',
                'data' => $property
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create estate',
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

        // Get properties for this estate
        $properties = Property::where(function($q) use ($id) {
            // Match by location hash or find properties in same location
            $q->where(DB::raw('md5(concat(location, city, state))'), $id);
        })->with(['allocations.member.user', 'images'])->get();

        if ($properties->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Estate not found'
            ], 404);
        }

        $first = $properties->first();
        $estate = [
            'id' => $id,
            'name' => $first->location ?? 'Unnamed Estate',
            'location' => ($first->city ?? '') . ', ' . ($first->state ?? ''),
            'total_properties' => $properties->count(),
            'properties' => $properties,
        ];

        return response()->json([
            'success' => true,
            'data' => $estate
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Update all properties in this estate
        $properties = Property::where(function($q) use ($id) {
            $q->where(DB::raw('md5(concat(location, city, state))'), $id);
        })->get();

        if ($properties->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Estate not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'state' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updated = 0;
            foreach ($properties as $property) {
                if ($request->has('name')) $property->location = $request->name;
                if ($request->has('city')) $property->city = $request->city;
                if ($request->has('state')) $property->state = $request->state;
                $property->save();
                $updated++;
            }

            return response()->json([
                'success' => true,
                'message' => "Updated {$updated} properties in estate",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update estate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        // Estates are not directly deletable, only individual properties
        return response()->json([
            'success' => false,
            'message' => 'Cannot delete estate. Delete individual properties instead.'
        ], 400);
    }

    public function stats(Request $request): JsonResponse
    {
       
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        try {
            $totalEstates = Property::whereNotNull('location')
                ->whereNotNull('city')
                ->whereNotNull('state')
                ->selectRaw('COUNT(DISTINCT CONCAT_WS("-", location, city, state)) as count')
                ->value('count') ?? 0;
        } catch (\Exception $e) {
            Log::error('Error calculating total estates: ' . $e->getMessage());
            $totalEstates = 0;
        }
            
        
        $totalProperties = Property::count();
        $allocatedProperties = PropertyAllocation::distinct('property_id')->count('property_id');
        $availableProperties = $totalProperties - $allocatedProperties;

        return response()->json([
            'success' => true,
            'data' => [
                'total_estates' => $totalEstates,
                'total_properties' => $totalProperties,
                'allocated_properties' => $allocatedProperties,
                'available_properties' => $availableProperties,
            ]
        ]);
    }
}

