<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class KycRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5MB max
            'document_types' => ['nullable', 'array'],
            'document_types.*' => ['string', 'in:passport,national_id,drivers_license,utility_bill,bank_statement'],
        ];
    }
}