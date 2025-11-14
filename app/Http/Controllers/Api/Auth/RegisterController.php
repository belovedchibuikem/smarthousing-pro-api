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

            // Create member profile
            $member = Member::create([
                'user_id' => $user->id,
                'member_number' => $this->registrationService->generateMemberNumber(),
                'staff_id' => $request->staff_id,
                'ippis_number' => $request->ippis_number,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'marital_status' => $request->marital_status,
                'nationality' => $request->nationality ?? 'Nigerian',
                'state_of_origin' => $request->state_of_origin,
                'lga' => $request->lga,
                'residential_address' => $request->residential_address,
                'city' => $request->city,
                'state' => $request->state,
                'rank' => $request->rank,
                'department' => $request->department,
                'command_state' => $request->command_state,
                'employment_date' => $request->employment_date,
                'years_of_service' => $request->years_of_service,
                'membership_type' => 'regular',
                'kyc_status' => 'pending',
            ]);

            // Send verification email
            $this->registrationService->sendVerificationEmail($user);

            DB::commit();

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Registration successful',
                'user' => new UserResource($user),
                'token' => $token,
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
