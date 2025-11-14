<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class InitializePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1',
            'currency' => 'sometimes|string|size:3',
            'payment_method' => 'required|in:paystack,remita,stripe,wallet,bank_transfer,manual',
            'description' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
        ];
    }
}
