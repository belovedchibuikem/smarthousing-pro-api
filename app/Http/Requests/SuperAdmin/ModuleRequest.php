<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class ModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:modules,slug,' . ($this->module?->id ?? ''),
            'description' => 'required|string|max:500',
            'icon' => 'required|string|max:50',
            'is_active' => 'boolean',
            'packages_count' => 'integer|min:0',
        ];
    }
}
