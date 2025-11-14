<?php

namespace App\Http\Requests\Properties;

use Illuminate\Foundation\Http\FormRequest;

class PropertyInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'interest_type' => 'required|in:rental,purchase,investment',
            'message' => 'nullable|string|max:500',
            'applicant' => 'required|array',
            'applicant.name' => 'required|string|max:255',
            'applicant.rank' => 'nullable|string|max:255',
            'applicant.pin' => 'nullable|string|max:100',
            'applicant.ippis_number' => 'nullable|string|max:100',
            'applicant.command' => 'nullable|string|max:255',
            'applicant.phone' => 'required|string|max:50',
            'financial' => 'required|array',
            'financial.net_salary' => 'required|numeric|min:0',
            'financial.has_existing_loan' => 'required|boolean',
            'financial.existing_loan_types' => 'nullable|array',
            'financial.existing_loan_types.*' => 'string|in:fmbn,fgshlb,home_renovation,cooperative,other',
            'next_of_kin' => 'required|array',
            'next_of_kin.name' => 'required|string|max:255',
            'next_of_kin.phone' => 'required|string|max:50',
            'next_of_kin.address' => 'required|string|max:500',
            'next_of_kin.relationship' => 'nullable|string|max:100',
            'property_snapshot' => 'required|array',
            'property_snapshot.title' => 'required|string|max:255',
            'property_snapshot.description' => 'nullable|string',
            'property_snapshot.type' => 'nullable|string|max:100',
            'property_snapshot.price' => 'nullable|numeric|min:0',
            'property_snapshot.size' => 'nullable|string|max:100',
            'property_snapshot.bedrooms' => 'nullable|integer|min:0',
            'funding_option' => 'required|string|in:equity_wallet,cash,loan,mix,mortgage,cooperative',
            'funding_breakdown' => 'nullable|array',
            'preferred_payment_methods' => 'nullable|array',
            'preferred_payment_methods.*' => 'string|in:equity_wallet,cash,loan,mix,mortgage,cooperative',
            'documents' => 'nullable|array',
            'documents.passport' => 'nullable|string',
            'documents.pay_slip' => 'nullable|string',
            'mortgage_id' => 'nullable|uuid|exists:mortgages,id',
            'signature' => 'required|array',
            'signature.data_url' => 'required|string',
            'signature.signed_at' => 'nullable|date',
            'mortgage' => 'nullable|array',
            'mortgage.provider' => 'nullable|string|max:255',
            'mortgage.tenure_years' => 'nullable|integer|min:1|max:35',
            'mortgage.interest_rate' => 'nullable|numeric|min:0|max:100',
            'mortgage.monthly_payment' => 'nullable|numeric|min:0',
            'mortgage.loan_amount' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'interest_type.in' => 'Interest type must be rental, purchase, or investment',
        ];
    }
}
