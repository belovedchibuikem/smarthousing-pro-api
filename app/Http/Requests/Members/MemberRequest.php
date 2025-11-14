<?php

namespace App\Http\Requests\Members;

use Illuminate\Foundation\Http\FormRequest;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|uuid|exists:users,id',
            'employee_id' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'nationality' => 'nullable|string|max:255',
            'state_of_origin' => 'nullable|string|max:255',
            'lga' => 'nullable|string|max:255',
            'residential_address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'rank' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'command_state' => 'nullable|string|max:255',
            'employment_date' => 'nullable|date',
            'years_of_service' => 'nullable|integer|min:0',
        ];
    }
}
