<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateAdminNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|uuid|exists:users,id',
            'type' => 'required|in:info,success,warning,error,system',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'data' => 'nullable|array',
            'mark_as_read' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'The user ID is required.',
            'user_id.uuid' => 'The user ID must be a valid UUID.',
            'user_id.exists' => 'The selected user does not exist.',
            'type.required' => 'The notification type is required.',
            'type.in' => 'The notification type must be one of: info, success, warning, error, system.',
            'title.required' => 'The notification title is required.',
            'title.max' => 'The notification title may not be greater than 255 characters.',
            'message.required' => 'The notification message is required.',
            'message.max' => 'The notification message may not be greater than 5000 characters.',
            'data.array' => 'The notification data must be an array.',
        ];
    }
}

