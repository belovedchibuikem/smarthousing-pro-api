<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

class SendMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_id' => 'required_without:save_as_draft|uuid|exists:users,id',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'type' => 'sometimes|in:internal,system,notification',
            'status' => 'sometimes|in:draft,sent',
            'save_as_draft' => 'sometimes|boolean',
            'cc' => 'sometimes|array',
            'cc.*' => 'uuid|exists:users,id',
            'bcc' => 'sometimes|array',
            'bcc.*' => 'uuid|exists:users,id',
            'category' => 'sometimes|string|max:255',
            'attachments' => 'sometimes|array',
            'attachments.*' => 'file|max:10240', // 10MB max per file
        ];
    }
}
