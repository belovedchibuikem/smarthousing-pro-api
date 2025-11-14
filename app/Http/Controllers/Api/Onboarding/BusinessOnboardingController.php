<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\BusinessOnboardingRequest;
use App\Http\Resources\Onboarding\BusinessOnboardingResource;
use App\Models\Central\Tenant;
use App\Models\Central\Subscription;
use App\Models\Central\Package;
use App\Models\Tenant\User;
use App\Models\Tenant\Member;
use App\Services\Auth\RegistrationService;
use App\Services\Communication\SuperAdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BusinessOnboardingController extends Controller
{
    public function __construct(
        protected RegistrationService $registrationService,
        protected SuperAdminNotificationService $notificationService
    ) {}

    public function store(BusinessOnboardingRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Create tenant
            $tenant = Tenant::create([
                'name' => $request->business_name,
                'slug' => $request->slug,
                'contact_email' => $request->contact_email,
                'contact_phone' => $request->contact_phone,
                'address' => $request->address,
                'status' => 'active',
                'subscription_status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'settings' => [
                    'timezone' => $request->timezone ?? 'Africa/Lagos',
                    'currency' => $request->currency ?? 'NGN',
                ],
            ]);

            // Create subscription
            $package = Package::find($request->package_id);
            if (!$package) {
                throw new \Exception('Package not found');
            }

            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'package_id' => $package->id,
                'status' => 'trial',
                'starts_at' => now(),
                'ends_at' => now()->addDays(14),
                'trial_ends_at' => now()->addDays(14),
                'amount' => $package->price,
            ]);

            // Create admin user in tenant database
            $adminUser = User::create([
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'first_name' => $request->admin_first_name,
                'last_name' => $request->admin_last_name,
                'phone' => $request->admin_phone,
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            // Create admin member profile
            $adminMember = Member::create([
                'user_id' => $adminUser->id,
                'member_number' => $this->registrationService->generateMemberNumber(),
                'staff_id' => $request->admin_staff_id,
                'ippis_number' => $request->admin_ippis_number,
                'date_of_birth' => $request->admin_date_of_birth,
                'gender' => $request->admin_gender,
                'marital_status' => $request->admin_marital_status,
                'nationality' => $request->admin_nationality ?? 'Nigerian',
                'state_of_origin' => $request->admin_state_of_origin,
                'lga' => $request->admin_lga,
                'residential_address' => $request->admin_residential_address,
                'city' => $request->admin_city,
                'state' => $request->admin_state,
                'rank' => $request->admin_rank,
                'department' => $request->admin_department,
                'command_state' => $request->admin_command_state,
                'employment_date' => $request->admin_employment_date,
                'years_of_service' => $request->admin_years_of_service,
                'membership_type' => 'premium',
                'kyc_status' => 'verified',
                'kyc_verified_at' => now(),
            ]);

            // Send verification email
            $this->registrationService->sendVerificationEmail($adminUser);

            DB::commit();

            // Notify super admins about new tenant registration
            $this->notificationService->notifyNewTenantRegistration(
                $tenant->id,
                $request->business_name,
                $package->name,
                $request->contact_email
            );

            return response()->json([
                'success' => true,
                'message' => 'Business created successfully. Please check your email to verify your account.',
                'data' => new BusinessOnboardingResource([
                    'tenant' => $tenant,
                    'subscription' => $subscription,
                    'admin_user' => $adminUser,
                    'admin_member' => $adminMember,
                ])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Business creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkSlugAvailability(string $slug): JsonResponse
    {
        $isAvailable = !Tenant::where('slug', $slug)->exists();
        
        return response()->json([
            'available' => $isAvailable,
            'suggestions' => $isAvailable ? [] : $this->generateSlugSuggestions($slug)
        ]);
    }

    private function generateSlugSuggestions(string $baseSlug): array
    {
        $suggestions = [];
        for ($i = 1; $i <= 5; $i++) {
            $suggestions[] = $baseSlug . '-' . $i;
        }
        return $suggestions;
    }
}
