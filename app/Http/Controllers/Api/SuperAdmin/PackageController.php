<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\PackageRequest;
use App\Http\Resources\SuperAdmin\PackageResource;
use App\Models\Central\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $packages = Package::with('modules')
            ->when($request->has('is_active'), function($query) use ($request) {
                return $query->where('is_active', $request->is_active);
            })
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'packages' => PackageResource::collection($packages),
            'pagination' => [
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
                'per_page' => $packages->perPage(),
                'total' => $packages->total(),
            ]
        ]);
    }

    public function store(PackageRequest $request): JsonResponse
    {
        $package = Package::create($request->validated());

        // Attach modules if provided
        if ($request->has('modules')) {
            $package->modules()->sync($request->modules);
        }

        return response()->json([
            'success' => true,
            'message' => 'Package created successfully',
            'package' => new PackageResource($package->load('modules'))
        ], 201);
    }

    public function show(Package $package): JsonResponse
    {
        $package->load('modules');
        
        return response()->json([
            'package' => new PackageResource($package)
        ]);
    }

    public function update(PackageRequest $request, Package $package): JsonResponse
    {
        $package->update($request->validated());

        // Update modules if provided
        if ($request->has('modules')) {
            $package->modules()->sync($request->modules);
        }

        return response()->json([
            'success' => true,
            'message' => 'Package updated successfully',
            'package' => new PackageResource($package->load('modules'))
        ]);
    }

    public function toggle(Package $package): JsonResponse
    {
        $package->update(['is_active' => !$package->is_active]);

        return response()->json([
            'success' => true,
            'message' => $package->is_active ? 'Package activated' : 'Package deactivated'
        ]);
    }

    public function destroy(Package $package): JsonResponse
    {
        // Check if package is in use
        if ($package->subscriptions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete package that is in use'
            ], 422);
        }

        $package->delete();

        return response()->json([
            'success' => true,
            'message' => 'Package deleted successfully'
        ]);
    }
}
