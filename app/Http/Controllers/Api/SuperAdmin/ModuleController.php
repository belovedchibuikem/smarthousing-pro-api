<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ModuleRequest;
use App\Http\Resources\SuperAdmin\ModuleResource;
use App\Models\Central\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Module::query();

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $modules = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'modules' => ModuleResource::collection($modules),
            'pagination' => [
                'current_page' => $modules->currentPage(),
                'last_page' => $modules->lastPage(),
                'per_page' => $modules->perPage(),
                'total' => $modules->total(),
            ]
        ]);
    }

    public function store(ModuleRequest $request): JsonResponse
    {
        $module = Module::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully',
            'module' => new ModuleResource($module)
        ], 201);
    }

    public function show(Module $module): JsonResponse
    {
        return response()->json([
            'module' => new ModuleResource($module)
        ]);
    }

    public function update(ModuleRequest $request, Module $module): JsonResponse
    {
        $module->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Module updated successfully',
            'module' => new ModuleResource($module)
        ]);
    }

    public function toggle(Module $module): JsonResponse
    {
        $module->update(['is_active' => !$module->is_active]);

        return response()->json([
            'success' => true,
            'message' => $module->is_active ? 'Module activated successfully' : 'Module deactivated successfully',
            'module' => new ModuleResource($module)
        ]);
    }

    public function destroy(Module $module): JsonResponse
    {
        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module deleted successfully'
        ]);
    }
}
