<?php

namespace App\Http\Controllers\Api\Properties;

use App\Http\Controllers\Controller;
use App\Http\Requests\Properties\PropertyInterestRequest;
use App\Models\Tenant\Property;
use App\Models\Tenant\PropertyInterest;
use App\Models\Tenant\Mortgage;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PropertyInterestController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}
    public function expressInterest(PropertyInterestRequest $request, String $propertyId): JsonResponse
    {
        $user =  $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $member = $user->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $property = Property::find($propertyId);
        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        // Check if property is available
        if ($property->status !== 'available') {
            return response()->json([
                'message' => 'Property is not available for interest expression'
            ], 400);
        }

        // Check if member has already expressed interest
        $existingInterest = PropertyInterest::where('property_id', $property->id)
            ->where('member_id', $member->id)
            ->first();

        if ($existingInterest) {
            return response()->json([
                'message' => 'You have already expressed interest in this property'
            ], 400);
        }

        $fundingOption = $request->string('funding_option')->toString();
        $mortgageData = $request->input('mortgage', []);

        $memberAge = null;
        if ($member->date_of_birth) {
            $memberAge = Carbon::parse($member->date_of_birth)->age;
        }

        $yearsOfService = $member->years_of_service;
        if ($yearsOfService === null && $member->employment_date) {
            $yearsOfService = Carbon::parse($member->employment_date)->diffInYears(now());
        }

        $yearsLeft = null;
        if ($yearsOfService !== null) {
            $yearsLeft = max(0, 35 - (int) $yearsOfService);
        }

        $selectedMortgage = null;
        if ($fundingOption === 'mortgage') {
            $mortgageId = $request->input('mortgage_id');
            if ($mortgageId) {
                $selectedMortgage = Mortgage::with('provider')
                    ->where('id', $mortgageId)
                    ->where(function ($query) use ($property) {
                        $query->whereNull('property_id')->orWhere('property_id', $property->id);
                    })
                    ->first();

                if (!$selectedMortgage) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Mortgage terms could not be found for this property.',
                        'code' => 'mortgage_not_available',
                    ], 422);
                }
            }

            if ($memberAge !== null && $memberAge >= 60) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mortgage funding is unavailable because the member is at or above the retirement age limit (60 years).',
                    'code' => 'mortgage_age_limit',
                ], 422);
            }

            if ($yearsOfService !== null && $yearsOfService >= 35) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mortgage funding is unavailable because the member has completed 35 years of service.',
                    'code' => 'mortgage_service_limit',
                ], 422);
            }
        }

        if ($fundingOption === 'mortgage') {
            $requestedTenure = isset($mortgageData['tenure_years']) ? (int) $mortgageData['tenure_years'] : null;
            if ($yearsLeft !== null && $yearsLeft <= 2 && $requestedTenure !== null && $requestedTenure >= 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mortgage tenure cannot exceed the member’s remaining service years. Please choose a shorter tenure or a different funding option.',
                    'code' => 'mortgage_tenure_restriction',
                ], 422);
            }
        }

        $applicantSnapshot = array_merge([
            'name' => trim(($member->user->first_name ?? '') . ' ' . ($member->user->last_name ?? '')),
            'member_number' => $member->member_number,
            'staff_id' => $member->staff_id,
            'pin' => $member->staff_id,
            'ippis_number' => $member->ippis_number,
            'rank' => $member->rank,
            'command' => $member->command_state ?? $member->department,
            'phone' => $member->user->phone ?? null,
            'email' => $member->user->email ?? null,
        ], $request->input('applicant', []));

        $nextOfKinSnapshot = array_merge([
            'name' => $member->next_of_kin_name,
            'relationship' => $member->next_of_kin_relationship,
            'phone' => $member->next_of_kin_phone,
            'email' => $member->next_of_kin_email,
            'address' => $member->next_of_kin_address,
        ], $request->input('next_of_kin', []));

        $propertySnapshot = array_merge([
            'id' => $property->id,
            'title' => $property->title,
            'description' => $property->description,
            'type' => $property->type,
            'location' => $property->location,
            'address' => $property->address,
            'city' => $property->city,
            'state' => $property->state,
            'price' => $property->price,
            'size' => $property->size,
            'bedrooms' => $property->bedrooms,
            'bathrooms' => $property->bathrooms,
            'images' => $property->images?->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->url,
                'is_primary' => $image->is_primary,
                'caption' => $image->alt_text,
            ])->toArray(),
        ], $request->input('property_snapshot', []));

        $documents = [];
        $passportDocument = $this->storeDataUrl($request->input('documents.passport'), 'property-interests/passports');
        if ($passportDocument) {
            $documents['passport'] = $passportDocument;
        }

        $paySlipDocument = $this->storeDataUrl($request->input('documents.pay_slip'), 'property-interests/payslips');
        if ($paySlipDocument) {
            $documents['pay_slip'] = $paySlipDocument;
        }

        $signatureDocument = $this->storeDataUrl($request->input('signature.data_url'), 'property-interests/signatures');
        $signedAt = $request->input('signature.signed_at')
            ? Carbon::parse($request->input('signature.signed_at'))
            : now();

        $preferredPaymentMethods = $request->input('preferred_payment_methods');
        if (!$preferredPaymentMethods || !is_array($preferredPaymentMethods) || empty($preferredPaymentMethods)) {
            if ($fundingOption === 'mix') {
                $preferredPaymentMethods = ['equity_wallet', 'loan'];
            } elseif ($fundingOption === 'mortgage') {
                $preferredPaymentMethods = ['mortgage'];
            } else {
                $preferredPaymentMethods = [$fundingOption];
            }
        }

        $mortgagePreferences = [];
        if ($selectedMortgage) {
            $mortgagePreferences = [
                'mortgage_id' => $selectedMortgage->id,
                'status' => $selectedMortgage->status,
                'loan_amount' => (float) $selectedMortgage->loan_amount,
                'interest_rate' => (float) $selectedMortgage->interest_rate,
                'tenure_years' => (int) $selectedMortgage->tenure_years,
                'monthly_payment' => (float) $selectedMortgage->monthly_payment,
                'provider' => $selectedMortgage->provider ? [
                    'id' => $selectedMortgage->provider->id,
                    'name' => $selectedMortgage->provider->name,
                    'contact_email' => $selectedMortgage->provider->contact_email,
                    'contact_phone' => $selectedMortgage->provider->contact_phone,
                ] : null,
            ];
        } elseif (!empty($mortgageData)) {
            $loanAmount = $mortgageData['loan_amount'] ?? (float) ($propertySnapshot['price'] ?? 0);
            $loanAmount = max(0, (float) $loanAmount);

            $mortgagePreferences = $mortgageData;
            $mortgagePreferences['loan_amount'] = $loanAmount;
            $mortgagePreferences['monthly_payment'] = $this->calculateAmortizedPayment(
                $loanAmount,
                $mortgagePreferences['interest_rate'] ?? null,
                $mortgagePreferences['tenure_years'] ?? null
            );
        }

        $mortgageFlagged = false;
        if (in_array('mortgage', $preferredPaymentMethods, true)) {
            if (($memberAge !== null && $memberAge >= 58) || ($yearsLeft !== null && $yearsLeft <= 5)) {
                $mortgageFlagged = true;
            }
        }

        // Create property interest
        $interest = PropertyInterest::create([
            'property_id' => $property->id,
            'member_id' => $member->id,
            'interest_type' => $request->interest_type,
            'message' => $request->message,
            'status' => 'pending',
            'priority' => $this->calculatePriority($member, $property),
            'applicant_snapshot' => $applicantSnapshot,
            'next_of_kin_snapshot' => $nextOfKinSnapshot,
            'net_salary' => $request->input('financial.net_salary'),
            'has_existing_loan' => $request->boolean('financial.has_existing_loan'),
            'existing_loan_types' => $request->input('financial.existing_loan_types', []),
            'property_snapshot' => $propertySnapshot,
            'funding_option' => $fundingOption,
            'funding_breakdown' => $request->input('funding_breakdown'),
            'preferred_payment_methods' => $preferredPaymentMethods,
            'documents' => $documents,
            'signature_path' => $signatureDocument['path'] ?? null,
            'signed_at' => $signedAt,
            'mortgage_preferences' => !empty($mortgagePreferences) ? $mortgagePreferences : null,
            'mortgage_flagged' => $mortgageFlagged,
        ]);

        $interest->load(['member.user', 'property']);

        // Notify admins about new property interest submission
        if ($interest->member && $interest->member->user) {
            $memberName = trim($interest->member->first_name . ' ' . $interest->member->last_name);
            $propertyTitle = $interest->property->title ?? 'property';
            
            $this->notificationService->notifyAdmins(
                'info',
                'New Property Interest Submission',
                "A new property interest for {$propertyTitle} has been submitted by {$memberName}",
                [
                    'interest_id' => $interest->id,
                    'property_id' => $interest->property_id,
                    'property_title' => $propertyTitle,
                    'member_id' => $interest->member_id,
                    'member_name' => $memberName,
                    'interest_type' => $interest->interest_type,
                    'funding_option' => $interest->funding_option,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Interest expressed successfully',
            'interest' => [
                'id' => $interest->id,
                'property_id' => $property->id,
                'interest_type' => $interest->interest_type,
                'status' => $interest->status,
                'priority' => $interest->priority,
                'funding_option' => $interest->funding_option,
                'preferred_payment_methods' => $interest->preferred_payment_methods,
                'signature_url' => $interest->signature_path ? Storage::disk('public')->url($interest->signature_path) : null,
                'mortgage_flagged' => $interest->mortgage_flagged,
                'mortgage_preferences' => $interest->mortgage_preferences,
                'created_at' => $interest->created_at,
            ]
        ]);
    }

    public function getMyInterests(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $interests = PropertyInterest::where('member_id', $member->id)
            ->with(['property'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'interests' => $interests->map(function ($interest) {
                return [
                    'id' => $interest->id,
                    'property' => [
                        'id' => $interest->property->id,
                        'title' => $interest->property->title,
                        'location' => $interest->property->location,
                        'price' => $interest->property->price,
                        'type' => $interest->property->type,
                    ],
                    'interest_type' => $interest->interest_type,
                    'status' => $interest->status,
                    'priority' => $interest->priority,
                    'created_at' => $interest->created_at,
                ];
            })
        ]);
    }

    public function getPropertyMortgage(Request $request, String $propertyId): JsonResponse
    {

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $property = Property::find($propertyId);
        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        $mortgage = Mortgage::with('provider')
            ->where('property_id', $property->id)
            ->whereIn('status', ['approved', 'active', 'pending'])
            ->orderByRaw("CASE status WHEN 'approved' THEN 1 WHEN 'active' THEN 2 WHEN 'pending' THEN 3 ELSE 4 END")
            ->latest('updated_at')
            ->first();

        if (!$mortgage) {
            return response()->json([
                'success' => true,
                'mortgage' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'mortgage' => [
                'id' => $mortgage->id,
                'status' => $mortgage->status,
                'loan_amount' => (float) $mortgage->loan_amount,
                'interest_rate' => (float) $mortgage->interest_rate,
                'tenure_years' => (int) $mortgage->tenure_years,
                'monthly_payment' => (float) $mortgage->monthly_payment,
                'provider' => $mortgage->provider ? [
                    'id' => $mortgage->provider->id,
                    'name' => $mortgage->provider->name,
                    'contact_email' => $mortgage->provider->contact_email,
                    'contact_phone' => $mortgage->provider->contact_phone,
                ] : null,
                'notes' => $mortgage->notes,
                'updated_at' => optional($mortgage->updated_at)->toDateTimeString(),
            ],
        ]);
    }

    public function withdrawInterest(Request $request, String $interestId): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;

        $interest = PropertyInterest::find($interestId);
        if (!$interest) {
            return response()->json(['message' => 'Interest not found'], 404);
        }

        if (!$member || $interest->member_id !== $member->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($interest->status === 'withdrawn') {
            return response()->json([
                'message' => 'Interest has already been withdrawn'
            ], 400);
        }

        $interest->update(['status' => 'withdrawn']);

        return response()->json([
            'success' => true,
            'message' => 'Interest withdrawn successfully'
        ]);
    }

    private function calculatePriority($member, $property): int
    {
        $priority = 0;

        // Higher priority for premium/VIP members
        if ($member->membership_type === 'vip') {
            $priority += 100;
        } elseif ($member->membership_type === 'premium') {
            $priority += 50;
        }

        // Higher priority for longer membership
        $membershipMonths = $member->created_at->diffInMonths(now());
        $priority += min($membershipMonths, 60); // Cap at 60 months

        // Higher priority for more contributions
        $totalContributions = $member->contributions()
            ->where('status', 'approved')
            ->sum('amount');
        $priority += min($totalContributions / 10000, 50); // Cap at 50 points

        return (int) $priority;
    }
    private function storeDataUrl(?string $dataUrl, string $directory): ?array
    {
        if (empty($dataUrl) || !is_string($dataUrl) || !str_contains($dataUrl, ';base64,')) {
            return null;
        }

        [$meta, $encoded] = explode(';base64,', $dataUrl);
        $mime = str_replace('data:', '', $meta);

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            default => 'bin',
        };

        $binary = base64_decode($encoded, true);
        if ($binary === false) {
            return null;
        }

        $filename = Str::uuid() . '.' . $extension;
        $path = "{$directory}/{$filename}";

        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'mime' => $mime,
            'url' => Storage::disk('public')->url($path),
        ];
    }

    private function calculateAmortizedPayment(float $loanAmount, ?float $interestRate, ?int $tenureYears): ?float
    {
        if ($loanAmount <= 0 || !$tenureYears || $tenureYears <= 0) {
            return null;
        }

        $numberOfPayments = $tenureYears * 12;
        $rate = $interestRate ? ($interestRate / 100) / 12 : 0.0;

        if ($rate <= 0) {
            return round($loanAmount / $numberOfPayments, 2);
        }

        $factor = pow(1 + $rate, $numberOfPayments);

        if ($factor === 1.0) {
            return round($loanAmount / $numberOfPayments, 2);
        }

        $payment = $loanAmount * ($rate * $factor) / ($factor - 1);

        return round($payment, 2);
    }
}
