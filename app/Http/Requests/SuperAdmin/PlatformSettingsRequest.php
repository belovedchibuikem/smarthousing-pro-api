<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class PlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => 'required|string|max:255|unique:platform_settings,key,' . $this->route('setting')?->id,
            'value' => 'required',
            'type' => 'required|string|in:string,boolean,integer,json',
            'category' => 'required|string|in:general,email,security,notifications,database',
            'description' => 'nullable|string|max:500',
            'is_public' => 'boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'key.required' => 'Setting key is required',
            'key.unique' => 'Setting key already exists',
            'value.required' => 'Setting value is required',
            'type.required' => 'Setting type is required',
            'type.in' => 'Setting type must be one of: string, boolean, integer, json',
            'category.required' => 'Setting category is required',
            'category.in' => 'Setting category must be one of: general, email, security, notifications, database'
        ];
    }
}
