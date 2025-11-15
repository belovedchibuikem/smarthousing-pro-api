<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\PackageResource;
use App\Models\Central\Package;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    /**
     * Get packages for onboarding page
     */
    public function packages(): JsonResponse
    {
        $packages = Package::where('is_active', true)
            ->with('modules')
            ->orderBy('price', 'asc')
            ->get();
        
        return response()->json([
            'success' => true,
            'packages' => PackageResource::collection($packages)
        ]);
    }
}
