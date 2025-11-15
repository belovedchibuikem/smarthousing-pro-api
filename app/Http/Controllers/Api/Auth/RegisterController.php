<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\Tenant\User;
use App\Models\Tenant\Member;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function __construct(
        protected RegistrationService $registrationService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Create user
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'role' => 'member',
                'status' => 'active',
            ]);

            // Create member profile with all available fields (all optional)
            $member = Member::create([
                'user_id' => $user->id,
                'member_number' => $this->registrationService->generateMemberNumber(),
                'staff_id' => $request->staff_number ?? $request->staff_id ?? null,
                'ippis_number' => $request->ippis_number ?? null,
                'date_of_birth' => $request->date_of_birth ?? null,
                'gender' => $request->gender ?? null,
                'marital_status' => $request->marital_status ?? null,
                'nationality' => $request->nationality ?? 'Nigerian',
                'state_of_origin' => $request->state_of_origin ?? null,
                'lga' => $request->lga ?? null,
                'residential_address' => $request->residential_address ?? null,
                'city' => $request->city ?? null,
                'state' => $request->state ?? null,
                'rank' => $request->rank ?? null,
                'department' => $request->command_department ?? $request->department ?? null,
                'command_state' => $request->command_state ?? null,
                'employment_date' => $request->date_of_first_employment ?? $request->employment_date ?? null,
                'years_of_service' => $request->years_of_service ?? null,
                'membership_type' => $request->membership_type === 'non-member' ? 'non-member' : ($request->membership_type ?? 'regular'),
                'kyc_status' => 'pending',
                'next_of_kin_name' => $request->nok_name ?? null,
                'next_of_kin_relationship' => $request->nok_relationship ?? null,
                'next_of_kin_phone' => $request->nok_phone ?? null,
                'next_of_kin_email' => $request->nok_email ?? null,
                'next_of_kin_address' => $request->nok_address ?? null,
            ]);

            // Generate and send OTP for email verification
            $this->registrationService->generateAndSendOtp($user, 'registration', $request->phone);

            DB::commit();

            // Don't return token yet - user must verify OTP first
            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please verify your email with the OTP sent to your email address.',
                'user' => new UserResource($user),
                'requires_otp_verification' => true,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
