<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gateway_type' => 'required|in:paystack,remita,stripe,manual',
            'is_enabled' => 'required|boolean',
            'is_test_mode' => 'required|boolean',
            'credentials' => 'required|array',
            'configuration' => 'nullable|array',
        ];
    }
}
