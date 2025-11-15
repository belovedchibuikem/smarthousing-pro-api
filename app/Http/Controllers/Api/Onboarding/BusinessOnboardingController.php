<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\BusinessOnboardingRequest;
use App\Http\Resources\Onboarding\BusinessOnboardingResource;
use App\Models\Central\Tenant;
use App\Models\Central\Subscription;
use App\Models\Central\Package;
use App\Models\Central\PlatformTransaction;
use App\Models\Tenant\User;
use App\Models\Tenant\Member;
use App\Services\SuperAdmin\SuperAdminPaymentService;
use App\Services\Communication\SuperAdminNotificationService;
use App\Services\Tenant\TenantDatabaseService;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BusinessOnboardingController extends Controller
{
    public function __construct(
        protected SuperAdminPaymentService $paymentService,
        protected SuperAdminNotificationService $notificationService,
        protected TenantDatabaseService $databaseService,
        protected RegistrationService $registrationService
    ) {}

    public function store(BusinessOnboardingRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Get package
            $package = Package::findOrFail($request->package_id);

            // Create tenant with pending_approval status
            $tenant = Tenant::create([
                'id' => Str::uuid(),
                'data' => [
                    'name' => $request->business_name,
                    'slug' => $request->slug,
                    'contact_email' => $request->contact_email,
                    'contact_phone' => $request->contact_phone,
                    'address' => $request->address,
                    'status' => 'pending_approval', // NOT active - waiting for admin approval
                    'subscription_status' => 'pending',
                    'admin_email' => $request->admin_email,
                    'admin_first_name' => $request->admin_first_name,
                    'admin_last_name' => $request->admin_last_name,
                    'admin_phone' => $request->admin_phone,
                    'admin_password' => bcrypt($request->admin_password), // Store hashed password
                    'admin_staff_id' => $request->admin_staff_id,
                    'admin_ippis_number' => $request->admin_ippis_number,
                    'admin_date_of_birth' => $request->admin_date_of_birth,
                    'admin_gender' => $request->admin_gender,
                    'admin_marital_status' => $request->admin_marital_status,
                    'admin_nationality' => $request->admin_nationality ?? 'Nigerian',
                    'admin_state_of_origin' => $request->admin_state_of_origin,
                    'admin_lga' => $request->admin_lga,
                    'admin_residential_address' => $request->admin_residential_address,
                    'admin_city' => $request->admin_city,
                    'admin_state' => $request->admin_state,
                    'admin_rank' => $request->admin_rank,
                    'admin_department' => $request->admin_department,
                    'admin_command_state' => $request->admin_command_state,
                    'admin_employment_date' => $request->admin_employment_date,
                    'admin_years_of_service' => $request->admin_years_of_service,
                    'settings' => [
                        'timezone' => $request->timezone ?? 'Africa/Lagos',
                        'currency' => $request->currency ?? 'NGN',
                    ],
                ],
            ]);

            // Create domain for the tenant based on slug or custom domain
            $this->createTenantDomain($tenant, $request->slug);

            // Create tenant database immediately
            Log::info('Creating tenant database during onboarding', [
                'tenant_id' =>  $request->slug,
            ]);

            $this->databaseService->createDatabaseFromSql($tenant);

            // Set up tenant database connection
            $this->databaseService->createDatabaseConnection( $request->slug . '_smart_housing');

            // Create admin user in tenant database
            $this->createAdminUser($tenant, $request);

            Log::info('Tenant database and admin user created successfully', [
                'tenant_id' => $tenant->id,
            ]);

            // Create subscription with pending status
            $billingCycle = $package->billing_cycle ?? 'monthly';
            $durationDays = match($billingCycle) {
                'weekly' => 7,
                'monthly' => 30,
                'quarterly' => 90,
                'yearly' => 365,
                default => 30
            };

            // Calculate dates - for pending subscriptions, set dates to now()
            // These will be properly recalculated when payment is approved
            $startsAt = now();
            $endsAt = $startsAt->copy()->addDays($durationDays);

            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'package_id' => $package->id,
                'status' => 'pending', // NOT active - waiting for payment approval
                'amount' => $package->price,
                'payment_method' => $request->payment_method ?? 'manual',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'current_period_start' => $startsAt,
                'current_period_end' => $endsAt,
                'metadata' => [
                    'package_name' => $package->name,
                    'billing_cycle' => $billingCycle,
                    'duration_days' => $durationDays,
                    'onboarding' => true,
                ],
            ]);

            // Initialize payment
            $paymentMethod = $request->payment_method ?? 'manual';
            $reference = 'ONB-' . time() . '-' . Str::random(8);

            // Create PlatformTransaction
            $transaction = PlatformTransaction::create([
                'tenant_id' => $tenant->id,
                'reference' => $reference,
                'type' => 'subscription',
                'amount' => $package->price,
                'currency' => 'NGN',
                'status' => $paymentMethod === 'manual' ? 'pending' : 'processing',
                'payment_gateway' => $paymentMethod === 'manual' ? 'manual' : $paymentMethod,
                'approval_status' => $paymentMethod === 'manual' ? 'pending' : null,
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'payment_method' => $paymentMethod,
                    'billing_cycle' => $billingCycle,
                    'duration_days' => $durationDays,
                    'onboarding' => true,
                    'admin_email' => $request->admin_email,
                    'business_name' => $request->business_name,
                ],
            ]);

            // Update subscription with payment reference
            $subscription->update([
                'payment_reference' => $reference,
            ]);

            // Initialize payment with gateway if not manual
            $paymentUrl = null;
            if ($paymentMethod !== 'manual') {
                $paymentData = $this->paymentService->initializeSubscriptionPayment(
                    $subscription,
                    $package->price,
                    $paymentMethod
                );

                if ($paymentData['success'] && isset($paymentData['data']['authorization_url'])) {
                    $paymentUrl = $paymentData['data']['authorization_url'];
                } elseif ($paymentData['success'] && isset($paymentData['data']['checkout_url'])) {
                    $paymentUrl = $paymentData['data']['checkout_url'];
                }
            }

            DB::commit();

            // Notify super admins about new tenant registration
            $this->notificationService->notifyNewTenantRegistration(
                $tenant->id,
                $request->business_name,
                $package->name,
                $request->contact_email
            );

            Log::info('Tenant onboarding completed', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'payment_reference' => $reference,
                'payment_method' => $paymentMethod,
            ]);

            return response()->json([
                'success' => true,
                'message' => $paymentMethod === 'manual' 
                    ? 'Tenant created successfully. Please complete payment and wait for admin approval.'
                    : 'Tenant created successfully. Please complete payment.',
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'payment_reference' => $reference,
                'payment_url' => $paymentUrl,
                'payment_method' => $paymentMethod,
                'amount' => $package->price,
                'next_steps' => $paymentMethod === 'manual' 
                    ? 'Upload payment evidence and wait for admin approval'
                    : 'Complete payment through the payment gateway',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Tenant onboarding failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Business creation failed',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred during registration',
            ], 500);
        }
    }

    public function checkSlugAvailability(string $slug): JsonResponse
    {
        // Check if slug exists in tenant data JSON field
        $exists = Tenant::whereRaw("JSON_EXTRACT(data, '$.slug') = ?", [$slug])->exists();
        $isAvailable = !$exists;
        
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

    /**
     * Create domain for the tenant based on slug or custom domain
     */
    private function createTenantDomain(Tenant $tenant, string $slug): void
    {
        try {
            // Determine the base domain based on environment
            $baseDomain = $this->getBaseDomain();
            
            // Create domain using slug: slug.localhost (dev) or slug.domain.com (prod)
            $domain = $slug . '.' . $baseDomain;
            
            // Create domain using the HasDomains trait
            $tenant->domains()->create([
                'domain' => $domain,
            ]);
            
            Log::info('Domain created for tenant', [
                'tenant_id' => $tenant->id,
                'slug' => $slug,
                'domain' => $domain,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to create domain for tenant', [
                'tenant_id' => $tenant->id,
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw - domain creation failure shouldn't block tenant creation
            // Domain can be created manually later if needed
        }
    }

    /**
     * Get the base domain for tenant subdomains
     */
    private function getBaseDomain(): string
    {
        // Check if we're in development (localhost)
        $appUrl = config('app.url', 'http://localhost');
        $isDevelopment = str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1');
        
        if ($isDevelopment) {
            return 'localhost';
        }
        
        // For production, use configured domain or default
        return config('app.domain', env('APP_DOMAIN', 'frschousing.com'));
    }

    /**
     * Create admin user in tenant database
     */
    private function createAdminUser(Tenant $tenant, BusinessOnboardingRequest $request): void
    {
        try {
            $tenantData = $tenant->data ?? [];
            
            // Get admin data from request
            $adminEmail = $request->admin_email;
            $adminPassword = bcrypt($request->admin_password); // Hash password
            $adminFirstName = $request->admin_first_name;
            $adminLastName = $request->admin_last_name;
            $adminPhone = $request->admin_phone;
            
            // Create admin user
            $adminUser = User::create([
                'email' => $adminEmail,
                'password' => $adminPassword,
                'first_name' => $adminFirstName,
                'last_name' => $adminLastName,
                'phone' => $adminPhone,
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            
            // Create admin member profile
            Member::create([
                'user_id' => $adminUser->id,
                'member_number' => $this->registrationService->generateMemberNumber(),
                'staff_id' => $request->admin_staff_id ?? null,
                'ippis_number' => $request->admin_ippis_number ?? null,
                'date_of_birth' => $request->admin_date_of_birth ?? null,
                'gender' => $request->admin_gender ?? null,
                'marital_status' => $request->admin_marital_status ?? null,
                'nationality' => $request->admin_nationality ?? 'Nigerian',
                'state_of_origin' => $request->admin_state_of_origin ?? null,
                'lga' => $request->admin_lga ?? null,
                'residential_address' => $request->admin_residential_address ?? null,
                'city' => $request->admin_city ?? null,
                'state' => $request->admin_state ?? null,
                'rank' => $request->admin_rank ?? null,
                'department' => $request->admin_department ?? null,
                'command_state' => $request->admin_command_state ?? null,
                'employment_date' => $request->admin_employment_date ?? null,
                'years_of_service' => $request->admin_years_of_service ?? null,
                'membership_type' => 'premium',
                'kyc_status' => 'verified',
                'kyc_verified_at' => now(),
            ]);
            
            Log::info('Admin user created in tenant database', [
                'tenant_id' => $tenant->id,
                'admin_user_id' => $adminUser->id,
                'admin_email' => $adminEmail,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to create admin user in tenant database', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
