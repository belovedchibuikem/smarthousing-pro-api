<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class SuperAdminPaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => 'required|boolean',
            'settings' => 'required|array',
            'settings.secret_key' => 'nullable|string',
            'settings.public_key' => 'nullable|string',
            'settings.publishable_key' => 'nullable|string',
            'settings.merchant_id' => 'nullable|string',
            'settings.api_key' => 'nullable|string',
            'settings.service_type_id' => 'nullable|string',
            'settings.webhook_secret' => 'nullable|string',
            'settings.test_mode' => 'nullable|boolean',
            'settings.bank_accounts' => 'nullable|array',
            'settings.bank_accounts.*' => 'nullable|array',
            'settings.bank_accounts.*.bank_name' => 'nullable|string|max:255',
            'settings.bank_accounts.*.account_number' => 'nullable|string|max:20',
            'settings.bank_accounts.*.account_name' => 'nullable|string|max:255',
            'settings.bank_accounts.*.account_type' => 'nullable|string|in:savings,current',
            'settings.require_payer_name' => 'nullable|boolean',
            'settings.require_payer_phone' => 'nullable|boolean',
            'settings.require_account_details' => 'nullable|boolean',
            'settings.require_payment_evidence' => 'nullable|boolean',
            'settings.account_details' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'is_active.required' => 'Gateway active status is required',
            'settings.required' => 'Gateway settings are required',
            'settings.array' => 'Gateway settings must be an array',
        ];
    }
}
