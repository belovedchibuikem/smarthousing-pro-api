<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class WalletTopUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:100|max:1000000',
            'payment_method' => 'required|in:card,bank_transfer,wallet',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Minimum top-up amount is ₦100',
            'amount.max' => 'Maximum top-up amount is ₦1,000,000',
            'payment_method.in' => 'Payment method must be card, bank_transfer, or wallet',
        ];
    }
}
