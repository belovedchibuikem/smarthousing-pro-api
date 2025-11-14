<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Member;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\Payment;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
	public function summary(): JsonResponse
	{
		$membersCount = Member::count();
		$walletsCount = Wallet::count();
		$totalWalletBalance = (float) Wallet::sum('balance');
		$paymentsCount = class_exists(Payment::class) ? Payment::count() : 0;
		$totalPaymentsAmount = class_exists(Payment::class) ? (float) Payment::sum('amount') : 0.0;

		return response()->json([
			'summary' => [
				'members' => $membersCount,
				'wallets' => $walletsCount,
				'total_wallet_balance' => $totalWalletBalance,
				'payments' => $paymentsCount,
				'total_payments_amount' => $totalPaymentsAmount,
			],
		]);
	}
}


