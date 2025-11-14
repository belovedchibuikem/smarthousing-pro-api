<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentGatewayAdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('payment_gateways', 'name')->ignore($this->gateway)],
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
            'supported_currencies' => ['nullable', 'array'],
            'supported_countries' => ['nullable', 'array'],
            'transaction_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'transaction_fee_fixed' => ['nullable', 'numeric', 'min:0'],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'],
            'maximum_amount' => ['nullable', 'numeric', 'min:0', 'gt:minimum_amount'],
            'platform_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'platform_fee_fixed' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}