<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;

class MembershipUpgradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_type' => 'required|in:premium,vip',
            'payment_method' => 'required|in:card,bank_transfer,wallet',
        ];
    }

    public function messages(): array
    {
        return [
            'membership_type.in' => 'Membership type must be either premium or vip',
            'payment_method.in' => 'Payment method must be card, bank_transfer, or wallet',
        ];
    }
}
