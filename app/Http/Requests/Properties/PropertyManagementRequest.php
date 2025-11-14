<?php

namespace App\Http\Requests\Properties;

use Illuminate\Foundation\Http\FormRequest;

class PropertyManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:house,apartment,land,commercial',
            'location' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'size' => 'nullable|string|max:100',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'status' => 'nullable|in:available,allocated,maintenance,sold',
            'images' => 'nullable|array',
            'images.*.url' => 'required_with:images|string|url',
            'images.*.caption' => 'nullable|string|max:255',
            'images.*.is_primary' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Property type must be one of: house, apartment, land, commercial',
            'status.in' => 'Property status must be one of: available, allocated, maintenance, sold',
            'price.min' => 'Property price must be a positive number',
        ];
    }
}
