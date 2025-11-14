<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Investment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RecommendationController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $recommendations = [];

        // Example heuristics (can be replaced with ML later):
        $wallet = $user->wallet;
        if ($wallet && (float) $wallet->balance > 0) {
            $recommendations[] = [
                'type' => 'investment',
                'title' => 'Consider allocating idle wallet balance',
                'detail' => 'You have a positive wallet balance. Consider investing a portion to earn returns.',
                'priority' => 'medium',
            ];
        }

        $activeLoans = Loan::whereHas('member', fn($q) => $q->where('user_id', $user->id))
            ->whereIn('status', ['approved', 'active'])
            ->count();
        if ($activeLoans === 0) {
            $recommendations[] = [
                'type' => 'loan',
                'title' => 'You may qualify for a loan',
                'detail' => 'Based on your profile, you may qualify for a housing loan. Review loan products.',
                'priority' => 'low',
            ];
        }

        $recentInvestments = Investment::whereHas('member', fn($q) => $q->where('user_id', $user->id))
            ->where('status', 'active')
            ->latest()->limit(1)->exists();
        if (!$recentInvestments) {
            $recommendations[] = [
                'type' => 'getting_started',
                'title' => 'Get started with investments',
                'detail' => 'Explore investment plans aligned with your budget and goals.',
                'priority' => 'low',
            ];
        }

        if ($user->member && $user->member->kyc_status !== 'approved') {
            $recommendations[] = [
                'type' => 'kyc',
                'title' => 'Complete your KYC',
                'detail' => 'Complete KYC to unlock loans and higher funding limits.',
                'priority' => 'high',
            ];
        }

        return response()->json([
            'success' => true,
            'recommendations' => $recommendations,
        ]);
    }
}


