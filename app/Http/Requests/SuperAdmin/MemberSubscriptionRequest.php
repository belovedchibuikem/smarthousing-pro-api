<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class MemberSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_id' => 'required|uuid|exists:tenants,id',
            'member_id' => 'required|uuid',
            'package_id' => 'required|string',
            'status' => 'sometimes|in:active,expired,cancelled',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'amount_paid' => 'required|numeric|min:0',
            'payment_method' => 'required|in:manual,paystack,remita,wallet',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
