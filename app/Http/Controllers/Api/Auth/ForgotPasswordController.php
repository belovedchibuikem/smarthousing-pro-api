<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Models\Tenant\OtpVerification;
use App\Mail\OtpEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ForgotPasswordController extends Controller
{
	public function sendResetLinkEmail(Request $request): JsonResponse
	{
		$request->validate([
			'email' => 'required|email|exists:users,email',
			'recaptcha_token' => 'required|string',
		]);

		// Verify reCAPTCHA
		$recaptchaService = app(\App\Services\RecaptchaService::class);
		$token = $request->input('recaptcha_token');
		$remoteIp = $request->ip();

		if (!$recaptchaService->verify($token, $remoteIp)) {
			return response()->json([
				'success' => false,
				'message' => 'reCAPTCHA verification failed. Please try again.',
			], 422);
		}

		$user = User::where('email', $request->email)->first();
		
		if (!$user) {
			return response()->json([
				'success' => false,
				'message' => 'User not found'
			], 404);
		}

		// Generate OTP for password reset
		$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
		$expiresAt = now()->addMinutes(10);

		// Store OTP
		OtpVerification::updateOrCreate(
			[
				'email' => $user->email,
				'type' => 'password_reset',
			],
			[
				'phone' => $user->phone ?? null,
				'otp' => $otp,
				'expires_at' => $expiresAt,
				'is_used' => false,
				'attempts' => 0,
			]
		);

		// Send OTP email
		try {
			Mail::to($user->email)->send(new OtpEmail($user, $otp, 'password_reset'));
		} catch (\Exception $e) {
			Log::error('Failed to send password reset OTP email: ' . $e->getMessage());
			Log::info("Password reset OTP for {$user->email}: {$otp}");
		}

		// Log OTP for development/testing
		Log::info("Password reset OTP generated for {$user->email}: {$otp}");

		return response()->json([
			'success' => true,
			'message' => 'Password reset OTP sent to your email address.',
			'expires_at' => $expiresAt->toIso8601String()
		]);
	}
}


