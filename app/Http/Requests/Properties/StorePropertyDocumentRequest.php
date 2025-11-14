<?php

namespace App\Http\Requests\Properties;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', 'uuid', 'exists:properties,id'],
            'member_id' => ['nullable', 'uuid', 'exists:members,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:10240'],
        ];
    }
}



