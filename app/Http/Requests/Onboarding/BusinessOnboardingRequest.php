<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class BusinessOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Business Information
            'business_name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/',
                function ($attribute, $value, $fail) {
                    // Check if slug exists in tenant data JSON field
                    $exists = \App\Models\Central\Tenant::whereRaw("JSON_EXTRACT(data, '$.slug') = ?", [$value])->exists();
                    if ($exists) {
                        $fail('This subdomain is already taken. Please choose a different one.');
                    }
                },
            ],
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'address' => 'required|string|max:1000',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:3',
            
            // Package Selection
            'package_id' => 'required|uuid|exists:packages,id',
            'payment_method' => 'nullable|string|in:manual,paystack,remita,stripe',
            
            // Admin Information
            'admin_first_name' => 'required|string|max:255',
            'admin_last_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|string|min:8|confirmed',
            'admin_phone' => 'nullable|string|max:20',
            'admin_staff_id' => 'nullable|string|max:255',
            'admin_ippis_number' => 'nullable|string|max:255',
            'admin_date_of_birth' => 'nullable|date',
            'admin_gender' => 'nullable|in:male,female,other',
            'admin_marital_status' => 'nullable|in:single,married,divorced,widowed',
            'admin_nationality' => 'nullable|string|max:255',
            'admin_state_of_origin' => 'nullable|string|max:255',
            'admin_lga' => 'nullable|string|max:255',
            'admin_residential_address' => 'nullable|string|max:1000',
            'admin_city' => 'nullable|string|max:255',
            'admin_state' => 'nullable|string|max:255',
            'admin_rank' => 'nullable|string|max:255',
            'admin_department' => 'nullable|string|max:255',
            'admin_command_state' => 'nullable|string|max:255',
            'admin_employment_date' => 'nullable|date',
            'admin_years_of_service' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'This subdomain is already taken. Please choose a different one.',
            'admin_password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
