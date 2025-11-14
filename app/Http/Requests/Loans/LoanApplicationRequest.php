<?php

namespace App\Http\Requests\Loans;

use Illuminate\Foundation\Http\FormRequest;

class LoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|uuid|exists:loan_products,id',
            'amount' => 'required|numeric|min:1000',
            'tenure_months' => 'required|integer|min:1|max:60',
            'purpose' => 'required|string|max:500',
            'net_pay' => 'required|numeric|min:0',
            'employment_status' => 'required|in:employed,self_employed,retired',
            'guarantor_name' => 'required|string|max:255',
            'guarantor_phone' => 'required|string|max:20',
            'guarantor_relationship' => 'required|string|max:100',
            'guarantor_address' => 'nullable|string|max:500',
            'additional_info' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Loan amount must be at least ₦1,000',
            'tenure_months.max' => 'Loan tenure cannot exceed 60 months',
            'net_pay.required' => 'Net pay information is required for loan eligibility',
        ];
    }
}
