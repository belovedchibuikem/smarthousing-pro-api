<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Tenant\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserSettingsController extends Controller
{
    /**
     * Get user settings
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $settings = UserSetting::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'email_notifications' => true,
                    'sms_notifications' => false,
                    'payment_reminders' => true,
                    'loan_updates' => true,
                    'investment_updates' => true,
                    'property_updates' => true,
                    'contribution_updates' => true,
                    'language' => 'en',
                    'timezone' => 'Africa/Lagos',
                    'two_factor_enabled' => false,
                    'profile_visible' => true,
                    'show_email' => false,
                    'show_phone' => false,
                ]
            );

            return response()->json([
                'success' => true,
                'settings' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error('UserSettingsController::index failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load settings',
            ], 500);
        }
    }

    /**
     * Update user settings
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'email_notifications' => 'sometimes|boolean',
                'sms_notifications' => 'sometimes|boolean',
                'payment_reminders' => 'sometimes|boolean',
                'loan_updates' => 'sometimes|boolean',
                'investment_updates' => 'sometimes|boolean',
                'property_updates' => 'sometimes|boolean',
                'contribution_updates' => 'sometimes|boolean',
                'language' => 'sometimes|string|in:en,ha,yo,ig',
                'timezone' => 'sometimes|string',
                'profile_visible' => 'sometimes|boolean',
                'show_email' => 'sometimes|boolean',
                'show_phone' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $settings = UserSetting::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'email_notifications' => true,
                    'sms_notifications' => false,
                    'payment_reminders' => true,
                    'loan_updates' => true,
                    'investment_updates' => true,
                    'property_updates' => true,
                    'contribution_updates' => true,
                    'language' => 'en',
                    'timezone' => 'Africa/Lagos',
                    'two_factor_enabled' => false,
                    'profile_visible' => true,
                    'show_email' => false,
                    'show_phone' => false,
                ]
            );

            $settings->update($request->only([
                'email_notifications',
                'sms_notifications',
                'payment_reminders',
                'loan_updates',
                'investment_updates',
                'property_updates',
                'contribution_updates',
                'language',
                'timezone',
                'profile_visible',
                'show_email',
                'show_phone',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'settings' => $settings->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('UserSettingsController::update failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 422);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('UserSettingsController::changePassword failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
            ], 500);
        }
    }

    /**
     * Enable/Disable Two-Factor Authentication
     */
    public function toggleTwoFactor(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $settings = UserSetting::firstOrCreate(['user_id' => $user->id]);

            if ($request->has('enabled')) {
                $enabled = filter_var($request->enabled, FILTER_VALIDATE_BOOLEAN);
                
                if ($enabled && !$settings->two_factor_secret) {
                    // Generate 2FA secret (simplified - can be enhanced with Google2FA package later)
                    $settings->two_factor_secret = bin2hex(random_bytes(16));
                    $settings->two_factor_recovery_codes = $this->generateRecoveryCodes();
                }

                $settings->two_factor_enabled = $enabled;
                $settings->save();

                return response()->json([
                    'success' => true,
                    'message' => $enabled ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled',
                    'two_factor_enabled' => $enabled,
                    'two_factor_secret' => $enabled ? $settings->two_factor_secret : null,
                    'recovery_codes' => $enabled ? $settings->two_factor_recovery_codes : null,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid request',
            ], 400);
        } catch (\Exception $e) {
            Log::error('UserSettingsController::toggleTwoFactor failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update two-factor authentication',
            ], 500);
        }
    }

    /**
     * Generate recovery codes for 2FA
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }
}
