<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\LoanProduct;
use App\Models\Tenant\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LoanProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $query = LoanProduct::query();

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($request->has('is_active') && $request->is_active !== 'all') {
                $query->where('is_active', $request->is_active === 'true');
            }

            $products = $query->withCount('loans')->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

            $data = $products->map(function ($product) {
                $totalLoans = Loan::where('product_id', $product->id)->sum('amount');
                $totalApplicants = Loan::where('product_id', $product->id)->distinct('member_id')->count('member_id');

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'min_amount' => (float) $product->min_amount,
                    'max_amount' => (float) $product->max_amount,
                    'interest_rate' => (float) $product->interest_rate,
                    'min_tenure_months' => $product->min_tenure_months,
                    'max_tenure_months' => $product->max_tenure_months,
                    'interest_type' => $product->interest_type,
                    'eligibility_criteria' => $product->eligibility_criteria ?? [],
                    'required_documents' => $product->required_documents ?? [],
                    'is_active' => (bool) $product->is_active,
                    'processing_fee_percentage' => $product->processing_fee_percentage,
                    'late_payment_fee' => (float) $product->late_payment_fee,
                    'loans_count' => $product->loans_count,
                    'total_loans' => $totalLoans,
                    'total_applicants' => $totalApplicants,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Admin LoanProductController::index failed', [
                'admin_id' => $user->id,
                'query' => $request->query(),
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to load loan products at the moment.'
                    : $exception->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|min:0|gte:min_amount',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'min_tenure_months' => 'required|integer|min:1',
            'max_tenure_months' => 'required|integer|min:1|gte:min_tenure_months',
            'interest_type' => 'required|string|in:simple,compound',
            'eligibility_criteria' => 'nullable|array',
            'required_documents' => 'nullable|array',
            'is_active' => 'boolean',
            'processing_fee_percentage' => 'nullable|integer|min:0|max:100',
            'late_payment_fee' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = LoanProduct::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Loan product created successfully',
                'data' => $product
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create loan product',
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

        try {
            $product = LoanProduct::with(['loans.member.user'])->findOrFail($id);
            $totalLoans = Loan::where('product_id', $product->id)->sum('amount');
            $totalApplicants = Loan::where('product_id', $product->id)->distinct('member_id')->count('member_id');

            $data = [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'min_amount' => (float) $product->min_amount,
                'max_amount' => (float) $product->max_amount,
                'interest_rate' => (float) $product->interest_rate,
                'min_tenure_months' => $product->min_tenure_months,
                'max_tenure_months' => $product->max_tenure_months,
                'interest_type' => $product->interest_type,
                'eligibility_criteria' => $product->eligibility_criteria ?? [],
                'required_documents' => $product->required_documents ?? [],
                'is_active' => (bool) $product->is_active,
                'processing_fee_percentage' => $product->processing_fee_percentage,
                'late_payment_fee' => (float) $product->late_payment_fee,
                'total_loans' => $totalLoans,
                'total_applicants' => $totalApplicants,
                'loans' => $product->loans,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch loan product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $product = LoanProduct::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'min_amount' => 'sometimes|required|numeric|min:0',
            'max_amount' => 'sometimes|required|numeric|min:0',
            'interest_rate' => 'sometimes|required|numeric|min:0|max:100',
            'min_tenure_months' => 'sometimes|required|integer|min:1',
            'max_tenure_months' => 'sometimes|required|integer|min:1',
            'interest_type' => 'sometimes|required|string|in:simple,compound',
            'eligibility_criteria' => 'nullable|array',
            'required_documents' => 'nullable|array',
            'is_active' => 'boolean',
            'processing_fee_percentage' => 'nullable|integer|min:0|max:100',
            'late_payment_fee' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->has('max_amount') && $request->has('min_amount') && 
                $request->max_amount < $request->min_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['max_amount' => ['Max amount must be greater than or equal to min amount']]
                ], 422);
            }

            if ($request->has('max_tenure_months') && $request->has('min_tenure_months') && 
                $request->max_tenure_months < $request->min_tenure_months) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['max_tenure_months' => ['Max tenure must be greater than or equal to min tenure']]
                ], 422);
            }

            $product->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Loan product updated successfully',
                'data' => $product->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update loan product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $product = LoanProduct::findOrFail($id);

        if ($product->loans()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete product with associated loans'
            ], 400);
        }

        try {
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Loan product deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete loan product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $product = LoanProduct::findOrFail($id);
            $product->update(['is_active' => !$product->is_active]);

            return response()->json([
                'success' => true,
                'message' => 'Loan product status updated successfully',
                'data' => $product->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

