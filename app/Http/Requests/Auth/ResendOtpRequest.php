<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'type' => 'nullable|in:registration,password_reset,email_verification',
            'phone' => 'nullable|string|max:20',
        ];
    }
}
