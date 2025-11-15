<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Models\Tenant\OtpVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ResetPasswordController extends Controller
{
	public function reset(Request $request): JsonResponse
	{
		$request->validate([
			'email' => 'required|email|exists:users,email',
			'otp' => 'required|string|size:6',
			'password' => 'required|confirmed|min:8',
		]);

		// Verify OTP
		$otpRecord = OtpVerification::where('email', $request->email)
			->where('otp', $request->otp)
			->where('type', 'password_reset')
			->where('expires_at', '>', now())
			->where('is_used', false)
			->first();

		if (!$otpRecord) {
			// Increment attempts if record exists but OTP is wrong
			$existingRecord = OtpVerification::where('email', $request->email)
				->where('type', 'password_reset')
				->where('expires_at', '>', now())
				->where('is_used', false)
				->first();
			
			if ($existingRecord) {
				$existingRecord->increment('attempts');
				
				if ($existingRecord->attempts >= 5) {
					$existingRecord->update(['is_used' => true]);
					return response()->json([
						'success' => false,
						'message' => 'Too many failed attempts. Please request a new OTP.'
					], 429);
				}
			}

			return response()->json([
				'success' => false,
				'message' => 'Invalid or expired OTP'
			], 400);
		}

		// Find user
		$user = User::where('email', $request->email)->first();
		if (!$user) {
			return response()->json([
				'success' => false,
				'message' => 'User not found'
			], 404);
		}

		// Update password
		$user->update([
			'password' => Hash::make($request->password),
		]);

		// Mark OTP as used
		$otpRecord->update(['is_used' => true]);

		Log::info("Password reset successful for user: {$user->email}");

		return response()->json([
			'success' => true,
			'message' => 'Password reset successfully. You can now login with your new password.'
		]);
	}
}


