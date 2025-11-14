<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\WhiteLabelPackageRequest;
use App\Http\Resources\SuperAdmin\WhiteLabelPackageResource;
use App\Models\Central\WhiteLabelPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhiteLabelPackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $packages = WhiteLabelPackage::when($request->has('is_active'), function($query) use ($request) {
                return $query->where('is_active', $request->is_active);
            })
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'packages' => WhiteLabelPackageResource::collection($packages),
            'pagination' => [
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
                'per_page' => $packages->perPage(),
                'total' => $packages->total(),
            ]
        ]);
    }

    public function store(WhiteLabelPackageRequest $request): JsonResponse
    {
        $package = WhiteLabelPackage::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'White label package created successfully',
            'package' => new WhiteLabelPackageResource($package)
        ], 201);
    }

    public function show(WhiteLabelPackage $whiteLabelPackage): JsonResponse
    {
        return response()->json([
            'package' => new WhiteLabelPackageResource($whiteLabelPackage)
        ]);
    }

    public function update(WhiteLabelPackageRequest $request, WhiteLabelPackage $whiteLabelPackage): JsonResponse
    {
        $whiteLabelPackage->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'White label package updated successfully',
            'package' => new WhiteLabelPackageResource($whiteLabelPackage)
        ]);
    }

    public function toggle(WhiteLabelPackage $whiteLabelPackage): JsonResponse
    {
        $whiteLabelPackage->update(['is_active' => !$whiteLabelPackage->is_active]);

        return response()->json([
            'success' => true,
            'message' => $whiteLabelPackage->is_active ? 'White label package activated' : 'White label package deactivated'
        ]);
    }

    public function destroy(WhiteLabelPackage $whiteLabelPackage): JsonResponse
    {
        // Check if package is in use (you might want to add this relationship)
        // if ($whiteLabelPackage->subscriptions()->exists()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Cannot delete white label package that is in use'
        //     ], 422);
        // }

        $whiteLabelPackage->delete();

        return response()->json([
            'success' => true,
            'message' => 'White label package deleted successfully'
        ]);
    }
}



