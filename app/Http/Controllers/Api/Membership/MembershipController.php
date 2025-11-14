<?php

namespace App\Http\Controllers\Api\Membership;

use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\MembershipUpgradeRequest;
use App\Models\Tenant\Member;
use App\Models\Tenant\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MembershipController extends Controller
{
    public function upgrade(MembershipUpgradeRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $member = $user->member;

            if (!$member) {
                return response()->json([
                    'message' => 'Member profile not found'
                ], 404);
            }

            // Check if member is already at the requested level or higher
            $currentLevel = $this->getMembershipLevel($member->membership_type);
            $requestedLevel = $this->getMembershipLevel($request->membership_type);

            if ($currentLevel >= $requestedLevel) {
                return response()->json([
                    'message' => 'You are already at this membership level or higher'
                ], 400);
            }

            // Calculate upgrade fee
            $upgradeFee = $this->calculateUpgradeFee($member->membership_type, $request->membership_type);

            // Create payment record
            $payment = Payment::create([
                'user_id' => $user->id,
                'amount' => $upgradeFee,
                'currency' => 'NGN',
                'type' => 'membership_upgrade',
                'status' => 'pending',
                'reference' => 'UPG-' . time() . '-' . rand(1000, 9999),
                'description' => "Membership upgrade from {$member->membership_type} to {$request->membership_type}",
                'metadata' => [
                    'from_membership_type' => $member->membership_type,
                    'to_membership_type' => $request->membership_type,
                    'upgrade_fee' => $upgradeFee,
                ],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Membership upgrade initiated',
                'payment' => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'reference' => $payment->reference,
                    'status' => $payment->status,
                ],
                'upgrade_details' => [
                    'from' => $member->membership_type,
                    'to' => $request->membership_type,
                    'fee' => $upgradeFee,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Membership upgrade failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getMembershipLevels(): JsonResponse
    {
        $levels = [
            'regular' => [
                'name' => 'Regular Member',
                'description' => 'Basic membership with standard benefits',
                'features' => [
                    'Access to basic loan products',
                    'Standard contribution rates',
                    'Basic property access',
                ],
                'upgrade_fee' => 0,
            ],
            'premium' => [
                'name' => 'Premium Member',
                'description' => 'Enhanced membership with additional benefits',
                'features' => [
                    'Access to premium loan products',
                    'Reduced interest rates',
                    'Priority property access',
                    'Enhanced customer support',
                ],
                'upgrade_fee' => 50000, // ₦50,000
            ],
            'vip' => [
                'name' => 'VIP Member',
                'description' => 'Premium membership with exclusive benefits',
                'features' => [
                    'Access to all loan products',
                    'Lowest interest rates',
                    'Exclusive property access',
                    'Dedicated account manager',
                    'Priority processing',
                ],
                'upgrade_fee' => 100000, // ₦100,000
            ],
        ];

        return response()->json([
            'levels' => $levels
        ]);
    }

    private function getMembershipLevel(string $membershipType): int
    {
        return match($membershipType) {
            'regular' => 1,
            'premium' => 2,
            'vip' => 3,
            default => 0,
        };
    }

    private function calculateUpgradeFee(string $from, string $to): float
    {
        $fees = [
            'regular_to_premium' => 50000,
            'regular_to_vip' => 100000,
            'premium_to_vip' => 50000,
        ];

        $key = $from . '_to_' . $to;
        return $fees[$key] ?? 0;
    }
}
