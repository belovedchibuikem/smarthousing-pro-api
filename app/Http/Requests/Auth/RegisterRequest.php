<?php

namespace App\Http\Requests\Auth;

use App\Services\RecaptchaService;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recaptcha_token' => ['required', 'string'],
            // Personal Information
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8'],
            'membership_type' => ['nullable', 'in:member,non-member'],
            
            // ID Information (for non-members)
            'id_type' => ['nullable', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:255'],
            
            // Employment Details (for members)
            'staff_number' => ['nullable', 'string', 'max:255'],
            'ippis_number' => ['nullable', 'string', 'max:255'],
            'date_of_first_employment' => ['nullable', 'date'],
            'years_of_service' => ['nullable', 'integer'],
            'command_department' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'rank' => ['nullable', 'string', 'max:255'],
            
            // Next of Kin
            'nok_name' => ['nullable', 'string', 'max:255'],
            'nok_relationship' => ['nullable', 'string', 'max:255'],
            'nok_phone' => ['nullable', 'string', 'max:20'],
            'nok_email' => ['nullable', 'email', 'max:255'],
            'nok_address' => ['nullable', 'string', 'max:1000'],
            
            // Additional fields
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other'],
            'marital_status' => ['nullable', 'in:single,married,divorced,widowed'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'state_of_origin' => ['nullable', 'string', 'max:255'],
            'lga' => ['nullable', 'string', 'max:255'],
            'residential_address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'command_state' => ['nullable', 'string', 'max:255'],
            'employment_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $recaptchaService = app(RecaptchaService::class);
            $token = $this->input('recaptcha_token');
            $remoteIp = $this->ip();

            if (!$recaptchaService->verify($token, $remoteIp)) {
                $validator->errors()->add('recaptcha_token', 'reCAPTCHA verification failed. Please try again.');
            }
        });
    }
}