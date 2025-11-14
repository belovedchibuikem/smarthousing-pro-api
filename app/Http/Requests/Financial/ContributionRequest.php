<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class ContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:100',
            'type' => 'required|in:monthly,quarterly,annual,special,emergency',
            'frequency' => 'required|in:monthly,quarterly,annually,one_time',
        ];
    }
}
