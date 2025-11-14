<?php

namespace App\Http\Controllers\Api\Investments;

use App\Http\Controllers\Controller;
use App\Http\Resources\Investments\InvestmentPlanResource;
use App\Models\Tenant\InvestmentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvestmentPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = InvestmentPlan::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'plans' => InvestmentPlanResource::collection($plans)
        ]);
    }

    public function show(InvestmentPlan $plan): JsonResponse
    {
        return response()->json([
            'plan' => new InvestmentPlanResource($plan)
        ]);
    }
}
