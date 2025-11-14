<?php

namespace App\Http\Requests\Loans;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanRepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'amount' => 'required|numeric|gt:0',
            'payment_method' => ['required', Rule::in(['wallet', 'card', 'bank_transfer'])],
            'notes' => 'nullable|string|max:500',
        ];

        if ($this->input('payment_method') === 'bank_transfer') {
            $rules['payer_name'] = 'required|string|max:255';
            $rules['payer_phone'] = 'nullable|string|max:50';
            $rules['transaction_reference'] = 'required|string|max:255';
            $rules['payment_evidence'] = 'required|array|min:1';
            $rules['payment_evidence.*'] = 'nullable|string|max:2048';
            $rules['bank_account_id'] = 'nullable|string|max:255';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'amount.gt' => 'Repayment amount must be greater than zero',
            'payment_method.in' => 'Payment method must be wallet, card, or bank_transfer',
            'payer_name.required' => 'Please provide the payer name for manual payments.',
            'transaction_reference.required' => 'Provide the bank transfer reference or narration.',
            'payment_evidence.required' => 'Upload at least one proof of payment.',
            'payment_evidence.array' => 'Payment evidence must be submitted as an array of file URLs.',
        ];
    }
}
