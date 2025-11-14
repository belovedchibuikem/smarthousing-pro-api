<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuperAdminManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $adminId = $this->route('admin') ? $this->route('admin')->id : null;
        
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('super_admins', 'email')->ignore($adminId)
            ],
            'password' => $adminId ? 'nullable|string|min:8' : 'required|string|min:8',
            'role' => 'required|string|exists:roles,id',
            'status' => 'nullable|string|in:active,inactive',
            'permissions' => 'nullable|array'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'name.max' => 'Name must not exceed 255 characters',
            'email.required' => 'Email is required',
            'email.email' => 'Email must be a valid email address',
            'email.unique' => 'Email is already taken',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'role.required' => 'Role is required',
            'role.in' => 'Invalid role selected',
            'status.in' => 'Invalid status selected'
        ];
    }
}



