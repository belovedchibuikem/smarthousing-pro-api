<?php

namespace App\Http\Requests\Investments;

use Illuminate\Foundation\Http\FormRequest;

class InvestmentWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'withdrawal_type' => 'required|in:full,partial',
            'amount' => 'required_if:withdrawal_type,partial|nullable|numeric|min:1000',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'withdrawal_type.in' => 'Withdrawal type must be full or partial',
            'amount.required_if' => 'Amount is required for partial withdrawal',
            'amount.min' => 'Minimum withdrawal amount is ₦1,000',
        ];
    }
}
