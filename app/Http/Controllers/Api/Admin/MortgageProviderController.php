<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Mortgage;
use App\Models\Tenant\MortgageProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MortgageProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = MortgageProvider::query()->whereNotNull('id');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

       
      
        if ($request->has('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'true');
        }

        
        
        $providers = $query->orderBy('name')->paginate($request->get('per_page', 15));
        
        return response()->json([
            'success' => true,
            'data' => $providers->items(),
            'pagination' => [
                'current_page' => $providers->currentPage(),
                'last_page' => $providers->lastPage(),
                'per_page' => $providers->perPage(),
                'total' => $providers->total(),
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string',
            'interest_rate_min' => 'nullable|numeric|min:0|max:100',
            'interest_rate_max' => 'nullable|numeric|min:0|max:100|gte:interest_rate_min',
            'min_loan_amount' => 'nullable|numeric|min:0',
            'max_loan_amount' => 'nullable|numeric|min:0|gte:min_loan_amount',
            'min_tenure_years' => 'nullable|integer|min:1',
            'max_tenure_years' => 'nullable|integer|min:1|gte:min_tenure_years',
            'requirements' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $provider = MortgageProvider::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Mortgage provider created successfully',
                'data' => $provider
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create mortgage provider',
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
            $provider = MortgageProvider::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $provider
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch mortgage provider',
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

        $provider = MortgageProvider::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string',
            'interest_rate_min' => 'nullable|numeric|min:0|max:100',
            'interest_rate_max' => 'nullable|numeric|min:0|max:100',
            'min_loan_amount' => 'nullable|numeric|min:0',
            'max_loan_amount' => 'nullable|numeric|min:0',
            'min_tenure_years' => 'nullable|integer|min:1',
            'max_tenure_years' => 'nullable|integer|min:1',
            'requirements' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->has('interest_rate_max') && $request->has('interest_rate_min') && 
                $request->interest_rate_max < $request->interest_rate_min) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['interest_rate_max' => ['Max interest rate must be greater than or equal to min interest rate']]
                ], 422);
            }

            if ($request->has('max_loan_amount') && $request->has('min_loan_amount') && 
                $request->max_loan_amount < $request->min_loan_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['max_loan_amount' => ['Max loan amount must be greater than or equal to min loan amount']]
                ], 422);
            }

            $provider->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Mortgage provider updated successfully',
                'data' => $provider->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update mortgage provider',
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

        try {
            $provider = MortgageProvider::findOrFail($id);

            // Check if provider has associated mortgages
            try {
                $mortgageCount = Mortgage::where('provider_id', $provider->id)->count();
                if ($mortgageCount > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete provider with associated mortgages'
                    ], 400);
                }
            } catch (\Exception $e) {
                // If relationship query fails, continue with delete
            }

            $provider->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mortgage provider deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete mortgage provider',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

