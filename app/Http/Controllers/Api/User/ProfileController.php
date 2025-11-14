<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ProfileRequest;
use App\Http\Resources\Auth\UserResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->load('member');
        }
        
        return response()->json([
            'user' => new UserResource($user)
        ]);
    }

    public function update(ProfileRequest $request): JsonResponse
    {
        
        try {
            $user = $request->user();

            $updateData = $request->only(['first_name', 'last_name', 'phone']);

            if ($request->has('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            // Update member profile if exists
            if ($user->member) {
                $memberData = $request->only([
                    'date_of_birth',
                    'gender',
                    'marital_status',
                    'nationality',
                    'state_of_origin',
                    'lga',
                    'residential_address',
                    'city',
                    'state',
                    'staff_id',
                    'ippis_number',
                    'rank',
                    'department',
                    'command_state',
                    'employment_date',
                    'years_of_service',
                    'next_of_kin_name',
                    'next_of_kin_relationship',
                    'next_of_kin_phone',
                    'next_of_kin_email',
                    'next_of_kin_address',
                ]);

                // Calculate years_of_service if employment_date is provided and not empty
                if (!empty($memberData['employment_date'])) {
                    $employmentDate = Carbon::parse($memberData['employment_date']);
                    $memberData['years_of_service'] = Carbon::now()->diffInYears($employmentDate);
                } elseif (array_key_exists('employment_date', $memberData) && empty($memberData['employment_date'])) {
                    // If employment_date is explicitly set to empty, clear years_of_service
                    $memberData['years_of_service'] = null;
                }

                // Filter out null values
                $memberData = array_filter(
                    $memberData,
                    static fn ($value) => !is_null($value)
                );

                if (!empty($memberData)) {
                    $user->member->update($memberData);
                }
            }

            $user->load('member');

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => new UserResource($user)
            ]);
        } catch (\Throwable $exception) {
            Log::error('ProfileController::update failed', [
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
                'tenant_id' => tenant('id'),
                'user_id' => optional(Auth::user())->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to update profile at the moment. Please try again later.'
                    : $exception->getMessage(),
            ], 500);
        }
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $user = $request->user();
        
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar_url' => $path]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'avatar_url' => $user->avatar_url
        ]);
    }

    /**
     * Upload payment evidence file
     */
    public function uploadPaymentEvidence(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120',
            ]);

            if (!$request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file provided',
                ], 400);
            }

            $file = $request->file('file');
            $tenantId = tenant('id');

            $baseDirectory = 'wallet-payments/evidence';
            if ($tenantId) {
                $baseDirectory = "tenants/{$tenantId}/{$baseDirectory}";
            }

            $storedPath = Storage::disk('public')->putFile($baseDirectory, $file);
            $fileUrl = Storage::disk('public')->url($storedPath);

            return response()
                ->json([
                    'success' => true,
                    'message' => 'File uploaded successfully',
                    'url' => $fileUrl,
                    'path' => $storedPath,
                ], 201);
        } catch (\Throwable $exception) {
            Log::error('ProfileController::uploadPaymentEvidence failed', [
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
                'tenant_id' => tenant('id'),
                'user_id' => optional(Auth::user())->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to upload payment evidence at the moment. Please try again later.'
                    : $exception->getMessage(),
            ], 500);
        }
    }
}