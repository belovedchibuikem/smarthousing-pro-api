<?php

namespace App\Http\Requests\Mail;

use Illuminate\Foundation\Http\FormRequest;

class ComposeMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_id' => 'required|uuid|exists:users,id',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'status' => 'nullable|in:draft,sent',
            'priority' => 'nullable|in:low,normal,high',
        ];
    }
}
