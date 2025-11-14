<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class InvestmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1000',
            'type' => 'required|in:savings,fixed_deposit,treasury_bills,bonds,stocks',
            'duration_months' => 'required|integer|min:1|max:120',
            'expected_return_rate' => 'required|numeric|min:0|max:50',
        ];
    }
}
