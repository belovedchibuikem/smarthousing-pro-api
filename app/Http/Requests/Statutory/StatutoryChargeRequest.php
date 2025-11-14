<?php

namespace App\Http\Requests\Statutory;

use Illuminate\Foundation\Http\FormRequest;

class StatutoryChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:1000',
            'due_date' => 'required|date|after:today',
        ];
    }
}
