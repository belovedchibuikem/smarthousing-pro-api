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
            'settings.webhook_secret' => 'nullable|string',
            'settings.test_mode' => 'nullable|boolean',
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
