<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class SuperAdminRoleRequest extends FormRequest
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
            'permissions' => 'required|array',
            'is_active' => 'nullable|boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Role name is required',
            'name.max' => 'Role name must not exceed 255 characters',
            'description.required' => 'Description is required',
            'description.max' => 'Description must not exceed 1000 characters',
            'permissions.required' => 'Permissions are required',
            'permissions.array' => 'Permissions must be an array'
        ];
    }
}
