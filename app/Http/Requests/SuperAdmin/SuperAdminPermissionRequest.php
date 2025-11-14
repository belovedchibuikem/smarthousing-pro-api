<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class SuperAdminPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionId = $this->route('permission') ? $this->route('permission')->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:permissions,name,' . $permissionId
            ],
            'guard_name' => [
                'nullable',
                'string',
                'max:255'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Permission name is required',
            'name.unique' => 'Permission name already exists',
            'name.max' => 'Permission name cannot exceed 255 characters',
            'guard_name.max' => 'Guard name cannot exceed 255 characters'
        ];
    }
}

