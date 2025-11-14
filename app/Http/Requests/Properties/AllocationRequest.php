<?php

namespace App\Http\Requests\Properties;

use Illuminate\Foundation\Http\FormRequest;

class AllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => 'required|exists:properties,id',
            'member_id' => 'required|exists:members,id',
            'allocation_date' => 'required|date|after_or_equal:today',
            'status' => 'sometimes|in:pending,approved,rejected,completed',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
