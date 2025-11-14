<?php

namespace App\Http\Requests\Properties;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in(['cash', 'equity_wallet', 'loan', 'mortgage', 'cooperative'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payer_phone' => ['nullable', 'string', 'max:50'],
            'payment_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $method = $this->input('method');

            if ($method === 'cash') {
                if (!$this->filled('payer_name')) {
                    $validator->errors()->add('payer_name', 'Please provide the payer name for manual payments.');
                }
            }
        });
    }
}

