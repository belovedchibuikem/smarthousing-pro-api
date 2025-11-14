<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class LoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1000',
            'interest_rate' => 'required|numeric|min:0|max:50',
            'duration_months' => 'required|integer|min:1|max:60',
            'type' => 'required|in:personal,housing,business,emergency',
            'purpose' => 'required|string|max:500',
        ];
    }
}
