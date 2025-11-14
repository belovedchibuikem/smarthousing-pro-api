<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Models\Tenant\User;
use App\Models\Tenant\OtpVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OtpController extends Controller
{
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $otpRecord = OtpVerification::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->where('is_used', false)
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ], 400);
        }

        // Mark OTP as used
        $otpRecord->update(['is_used' => true]);

        // Find user and verify email
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->update(['email_verified_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully',
            'email_verified' => true
        ]);
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Generate new OTP
        $otp = $this->generateOtp();
        $expiresAt = now()->addMinutes(10);

        // Create or update OTP record
        OtpVerification::updateOrCreate(
            ['email' => $request->email],
            [
                'otp' => Hash::make($otp),
                'expires_at' => $expiresAt,
                'is_used' => false,
                'attempts' => 0,
            ]
        );

        // Send OTP email
        $this->sendOtpEmail($user, $otp);

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'expires_at' => $expiresAt
        ]);
    }

    private function generateOtp(): string
    {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function sendOtpEmail(User $user, string $otp): void
    {
        // This would typically send an email with the OTP
        // For now, we'll just log it
        Log::info("OTP for {$user->email}: {$otp}");
    }
}
