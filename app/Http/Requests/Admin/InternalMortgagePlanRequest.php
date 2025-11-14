<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class InternalMortgagePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['nullable', 'uuid', 'exists:properties,id'],
            'member_id' => ['nullable', 'uuid', 'exists:members,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'principal' => ['required', 'numeric', 'min:0'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tenure_years' => ['required', 'integer', 'min:1', 'max:35'],
            'frequency' => ['required', 'in:monthly,quarterly,biannually,annually'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'metadata' => ['nullable', 'array'],
            'status' => ['nullable', 'in:draft,active,completed,cancelled'],
        ];
    }
}



