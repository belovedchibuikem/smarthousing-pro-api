<?php

namespace App\Http\Resources\Onboarding;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessOnboardingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tenant' => [
                'id' => $this->resource['tenant']->id,
                'name' => $this->resource['tenant']->name,
                'slug' => $this->resource['tenant']->slug,
                'contact_email' => $this->resource['tenant']->contact_email,
                'contact_phone' => $this->resource['tenant']->contact_phone,
                'address' => $this->resource['tenant']->address,
                'status' => $this->resource['tenant']->status,
                'subscription_status' => $this->resource['tenant']->subscription_status,
                'trial_ends_at' => $this->resource['tenant']->trial_ends_at,
                'created_at' => $this->resource['tenant']->created_at,
            ],
            'subscription' => [
                'id' => $this->resource['subscription']->id,
                'status' => $this->resource['subscription']->status,
                'starts_at' => $this->resource['subscription']->starts_at,
                'ends_at' => $this->resource['subscription']->ends_at,
                'trial_ends_at' => $this->resource['subscription']->trial_ends_at,
                'amount' => $this->resource['subscription']->amount,
            ],
            'admin_user' => [
                'id' => $this->resource['admin_user']->id,
                'email' => $this->resource['admin_user']->email,
                'first_name' => $this->resource['admin_user']->first_name,
                'last_name' => $this->resource['admin_user']->last_name,
                'phone' => $this->resource['admin_user']->phone,
                'role' => $this->resource['admin_user']->role,
                'status' => $this->resource['admin_user']->status,
            ],
            'admin_member' => [
                'id' => $this->resource['admin_member']->id,
                'member_number' => $this->resource['admin_member']->member_number,
                'staff_id' => $this->resource['admin_member']->staff_id,
                'ippis_number' => $this->resource['admin_member']->ippis_number,
                'membership_type' => $this->resource['admin_member']->membership_type,
                'kyc_status' => $this->resource['admin_member']->kyc_status,
            ],
        ];
    }
}
