<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'marital_status' => ['sometimes', 'nullable', 'string', 'max:20'],
            'nationality' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state_of_origin' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lga' => ['sometimes', 'nullable', 'string', 'max:100'],
            'residential_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'staff_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'ippis_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'rank' => ['sometimes', 'nullable', 'string', 'max:100'],
            'department' => ['sometimes', 'nullable', 'string', 'max:100'],
            'command_state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'employment_date' => ['sometimes', 'nullable', 'date'],
            'years_of_service' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'next_of_kin_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'next_of_kin_relationship' => ['sometimes', 'nullable', 'string', 'max:100'],
            'next_of_kin_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'next_of_kin_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'next_of_kin_address' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}