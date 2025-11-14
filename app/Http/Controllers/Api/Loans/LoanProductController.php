<?php

namespace App\Http\Controllers\Api\Loans;

use App\Http\Controllers\Controller;
use App\Http\Resources\Loans\LoanProductResource;
use App\Models\Tenant\LoanProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = LoanProduct::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'products' => LoanProductResource::collection($products)
        ]);
    }

    public function show(LoanProduct $product): JsonResponse
    {
        return response()->json([
            'product' => new LoanProductResource($product)
        ]);
    }
}
