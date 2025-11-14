<?php

namespace App\Http\Controllers\Api\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\InvestmentRequest;
use App\Http\Resources\Financial\InvestmentResource;
use App\Models\Tenant\Investment;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvestmentController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Investment::with(['member.user']);

        // Filter by user if not admin
        $user = $request->user();
        if ($user->role !== 'admin') {
            $query->whereHas('member', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by investment type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $investments = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'investments' => InvestmentResource::collection($investments),
            'pagination' => [
                'current_page' => $investments->currentPage(),
                'last_page' => $investments->lastPage(),
                'per_page' => $investments->perPage(),
                'total' => $investments->total(),
            ]
        ]);
    }

    public function store(InvestmentRequest $request): JsonResponse
    {
        $member = $request->user()->member;
        
        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $investment = Investment::create([
            'member_id' => $member->id,
            'amount' => $request->amount,
            'type' => $request->type,
            'duration_months' => $request->duration_months,
            'expected_return_rate' => $request->expected_return_rate,
            'status' => 'pending',
            'investment_date' => now(),
        ]);

        // Notify admins about new investment application
        $memberName = $member->first_name . ' ' . $member->last_name;
        $this->notificationService->notifyAdminsNewInvestment(
            $investment->id,
            $memberName,
            $request->amount
        );

        return response()->json([
            'success' => true,
            'message' => 'Investment application submitted successfully',
            'investment' => new InvestmentResource($investment->load('member.user'))
        ], 201);
    }

    public function show(Request $request, Investment $investment): JsonResponse
    {
        // Check if user can view this investment
        $user = $request->user();
        if ($user->role !== 'admin' && $investment->member->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $investment->load(['member.user', 'returns']);

        return response()->json([
            'investment' => new InvestmentResource($investment)
        ]);
    }

    public function update(InvestmentRequest $request, Investment $investment): JsonResponse
    {
        // Only allow updates if investment is pending
        if ($investment->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot update investment that is not pending'
            ], 400);
        }

        $investment->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Investment updated successfully',
            'investment' => new InvestmentResource($investment->load('member.user'))
        ]);
    }

    public function approve(Investment $investment): JsonResponse
    {
        if ($investment->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending investments can be approved'
            ], 400);
        }

        $investment->update([
            'status' => 'active',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Investment approved successfully'
        ]);
    }

    public function reject(Investment $investment): JsonResponse
    {
        $request = request();
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        if ($investment->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending investments can be rejected'
            ], 400);
        }

        $investment->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Investment rejected successfully'
        ]);
    }

    public function destroy(Investment $investment): JsonResponse
    {
        if ($investment->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot delete investment that is not pending'
            ], 400);
        }

        $investment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Investment deleted successfully'
        ]);
    }
}
