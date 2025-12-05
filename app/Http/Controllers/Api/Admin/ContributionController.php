<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contribution;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContributionController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $query = Contribution::with(['member.user', 'payments']);

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->whereHas('member.user', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })->orWhereHas('member', function ($q) use ($search) {
                    $q->where('member_id', 'like', "%{$search}%");
                });
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            $contributions = $query
                ->orderByDesc('contribution_date')
                ->orderByDesc('created_at')
                ->paginate($request->get('per_page', 15));

            // Add payment_method to each contribution from the first payment
            $contributionsData = collect($contributions->items())->map(function ($contribution) {
                // Get payment_method from the first payment if available
                $paymentMethod = null;
                if ($contribution->relationLoaded('payments') && $contribution->payments && $contribution->payments->isNotEmpty()) {
                    $paymentMethod = $contribution->payments->first()->payment_method;
                }
                
                // Convert to array and add payment_method
                $data = $contribution->toArray();
                if ($paymentMethod) {
                    $data['payment_method'] = $paymentMethod;
                }
                return $data;
            })->all();

            return response()->json([
                'success' => true,
                'data' => $contributionsData,
                'pagination' => [
                    'current_page' => $contributions->currentPage(),
                    'last_page' => $contributions->lastPage(),
                    'per_page' => $contributions->perPage(),
                    'total' => $contributions->total(),
                ]
            ]);
        } catch (\Throwable $exception) {
            Log::error('Admin ContributionController::index failed', [
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
                'admin_id' => $user->id,
                'query_params' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to fetch contributions at the moment.'
                    : $exception->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $contribution = Contribution::with(['member.user', 'payments'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $contribution
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $contribution = Contribution::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|string',
            'status' => 'sometimes|string',
            'payment_method' => 'nullable|string',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $contribution->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Contribution updated successfully',
            'data' => $contribution->fresh()->load(['member.user'])
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $contribution = Contribution::findOrFail($id);

        $contribution->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contribution deleted successfully'
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $contribution = Contribution::findOrFail($id);

        if ($contribution->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Contribution is already approved'
            ], 400);
        }

        $contribution->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        $contribution->load('member.user');

        // Notify the member about contribution approval
        if ($contribution->member && $contribution->member->user) {
            $this->notificationService->sendNotificationToUsers(
                [$contribution->member->user->id],
                'success',
                'Contribution Approved',
                'Your contribution of ₦' . number_format($contribution->amount, 2) . ' has been approved.',
                [
                    'contribution_id' => $contribution->id,
                    'amount' => $contribution->amount,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Contribution approved successfully',
            'data' => $contribution
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $contribution = Contribution::findOrFail($id);

        if ($contribution->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Contribution is already rejected'
            ], 400);
        }

        $contribution->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
            'rejected_by' => $user->id,
        ]);

        $contribution->load('member.user');

        // Notify the member about contribution rejection
        if ($contribution->member && $contribution->member->user) {
            $this->notificationService->sendNotificationToUsers(
                [$contribution->member->user->id],
                'warning',
                'Contribution Rejected',
                'Your contribution of ₦' . number_format($contribution->amount, 2) . ' has been rejected. Reason: ' . $validated['rejection_reason'],
                [
                    'contribution_id' => $contribution->id,
                    'amount' => $contribution->amount,
                    'reason' => $validated['rejection_reason'],
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Contribution rejected successfully',
            'data' => $contribution
        ]);
    }
}

