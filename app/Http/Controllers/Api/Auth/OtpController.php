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
            ->where('otp', $request->otp) // Plain OTP comparison
            ->where('expires_at', '>', now())
            ->where('is_used', false)
            ->first();

        if (!$otpRecord) {
            // Increment attempts if record exists but OTP is wrong
            $existingRecord = OtpVerification::where('email', $request->email)
                ->where('expires_at', '>', now())
                ->where('is_used', false)
                ->first();
            
            if ($existingRecord) {
                $existingRecord->increment('attempts');
                
                // Block after 5 failed attempts
                if ($existingRecord->attempts >= 5) {
                    $existingRecord->update(['is_used' => true]); // Mark as used to prevent further attempts
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

        // Mark OTP as used
        $otpRecord->update(['is_used' => true]);

        // Find user and verify email
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Verify email
        $user->update(['email_verified_at' => now()]);

        // Generate auth token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return token and user data
        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully',
            'email_verified' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => $user->role,
            ]
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

        // Determine OTP type from request or default to registration
        $type = $request->type ?? 'registration';

        // Generate new OTP
        $otp = $this->generateOtp();
        $expiresAt = now()->addMinutes(10);

        // Create or update OTP record (store plain OTP)
        OtpVerification::updateOrCreate(
            [
                'email' => $request->email,
                'type' => $type,
            ],
            [
                'phone' => $request->phone ?? null,
                'otp' => $otp, // Store plain OTP
                'expires_at' => $expiresAt,
                'is_used' => false,
                'attempts' => 0,
            ]
        );

        // Send OTP email
        $this->sendOtpEmail($user, $otp, $type);

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'expires_at' => $expiresAt->toIso8601String()
        ]);
    }

    private function generateOtp(): string
    {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function sendOtpEmail(User $user, string $otp, string $type = 'registration'): void
    {
        try {
            Mail::to($user->email)->send(new \App\Mail\OtpEmail($user, $otp, $type));
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email: ' . $e->getMessage());
            // Log OTP for development/testing
            Log::info("OTP for {$user->email} ({$type}): {$otp}");
        }
    }
}
