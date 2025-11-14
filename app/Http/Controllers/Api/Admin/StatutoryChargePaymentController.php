<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\StatutoryChargePayment;
use App\Models\Tenant\StatutoryCharge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StatutoryChargePaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = StatutoryChargePayment::with(['statutoryCharge.member.user']);

        if ($request->has('statutory_charge_id')) {
            $query->where('statutory_charge_id', $request->statutory_charge_id);
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $payments = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
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
            'statutory_charge_id' => 'required|uuid|exists:statutory_charges,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
            'status' => 'sometimes|string|in:pending,completed,failed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $charge = StatutoryCharge::findOrFail($request->statutory_charge_id);
            $totalPaid = $charge->total_paid + $request->amount;

            if ($totalPaid > $charge->amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds remaining balance'
                ], 400);
            }

            $payment = StatutoryChargePayment::create([
                'statutory_charge_id' => $request->statutory_charge_id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'reference' => $request->reference,
                'status' => $request->status ?? 'completed',
                'paid_at' => now(),
            ]);

            // Update charge status if fully paid
            if ($totalPaid >= $charge->amount) {
                $charge->update(['status' => 'paid']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => $payment->load(['statutoryCharge.member.user'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment',
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
            $payment = StatutoryChargePayment::with(['statutoryCharge.member.user'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

