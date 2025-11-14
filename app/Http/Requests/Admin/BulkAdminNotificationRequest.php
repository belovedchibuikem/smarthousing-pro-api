<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkAdminNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'required|uuid|exists:users,id',
            'type' => 'required|in:info,success,warning,error,system',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'data' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'At least one user ID is required.',
            'user_ids.array' => 'User IDs must be an array.',
            'user_ids.min' => 'At least one user ID is required.',
            'user_ids.max' => 'You cannot send notifications to more than 100 users at once.',
            'user_ids.*.required' => 'Each user ID is required.',
            'user_ids.*.uuid' => 'Each user ID must be a valid UUID.',
            'user_ids.*.exists' => 'One or more selected users do not exist.',
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

