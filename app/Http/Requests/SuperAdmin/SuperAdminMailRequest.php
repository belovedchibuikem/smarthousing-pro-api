<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class SuperAdminMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_type' => 'required|in:all_admins,specific_business,all_members',
            'business_id' => 'required_if:recipient_type,specific_business|nullable|string',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:10000',
            'template_id' => 'nullable|string'
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_type.required' => 'Recipient type is required',
            'recipient_type.in' => 'Invalid recipient type',
            'business_id.required_if' => 'Business ID is required when sending to specific business',
            'subject.required' => 'Subject is required',
            'subject.max' => 'Subject must not exceed 255 characters',
            'message.required' => 'Message is required',
            'message.max' => 'Message must not exceed 10000 characters'
        ];
    }
}



