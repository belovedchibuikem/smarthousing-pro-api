<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class WhiteLabelPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,quarterly,annually',
            'features' => 'required|array|min:1',
            'features.*' => 'string|max:255',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Package name is required',
            'description.required' => 'Package description is required',
            'price.required' => 'Package price is required',
            'price.numeric' => 'Package price must be a number',
            'price.min' => 'Package price must be at least 0',
            'billing_cycle.required' => 'Billing cycle is required',
            'billing_cycle.in' => 'Billing cycle must be monthly, quarterly, or annually',
            'features.required' => 'At least one feature is required',
            'features.array' => 'Features must be an array',
            'features.min' => 'At least one feature is required',
        ];
    }
}



