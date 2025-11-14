<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'uuid', Rule::exists('members', 'id')],
            'loan_id' => ['required', 'uuid', Rule::exists('loans', 'id')],
            'amount' => ['required', 'numeric', 'min:1'],
            'principal_paid' => ['nullable', 'numeric', 'min:0'],
            'interest_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:100'],
            'payment_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'Select a member to continue.',
            'loan_id.required' => 'Select a loan to continue.',
            'amount.min' => 'Repayment amount must be greater than zero.',
        ];
    }
}

