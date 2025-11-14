<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CustomDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain_name' => 'required|string|max:255|regex:/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/',
            'subdomain' => 'nullable|string|max:63|regex:/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/'
        ];
    }
}
