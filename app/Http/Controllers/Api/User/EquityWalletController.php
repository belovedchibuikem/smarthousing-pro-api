<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Tenant\EquityWalletBalance;
use App\Models\Tenant\EquityTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EquityWalletController extends Controller
{
    public function balance(Request $request): JsonResponse
    {
        $member = $request->user()->member;
        
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found'
            ], 404);
        }

        $wallet = EquityWalletBalance::firstOrCreate(
            ['member_id' => $member->id],
            [
                'balance' => 0,
                'total_contributed' => 0,
                'total_used' => 0,
                'currency' => 'NGN',
                'is_active' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => (float) $wallet->balance,
                'total_contributed' => (float) $wallet->total_contributed,
                'total_used' => (float) $wallet->total_used,
                'currency' => $wallet->currency,
                'is_active' => $wallet->is_active,
                'last_updated_at' => $wallet->last_updated_at,
            ]
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $member = $request->user()->member;
        
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found'
            ], 404);
        }

        $query = EquityTransaction::where('member_id', $member->id)
            ->latest('created_at');

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        $transactions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }
}

