<?php

namespace App\Http\Resources\Members;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_number' => $this->member_number,
            'staff_id' => $this->staff_id,
            'ippis_number' => $this->ippis_number,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'marital_status' => $this->marital_status,
            'nationality' => $this->nationality,
            'state_of_origin' => $this->state_of_origin,
            'lga' => $this->lga,
            'residential_address' => $this->residential_address,
            'city' => $this->city,
            'state' => $this->state,
            'rank' => $this->rank,
            'department' => $this->department,
            'command_state' => $this->command_state,
            'employment_date' => $this->employment_date,
            'years_of_service' => $this->years_of_service,
            'next_of_kin_name' => $this->next_of_kin_name,
            'next_of_kin_relationship' => $this->next_of_kin_relationship,
            'next_of_kin_phone' => $this->next_of_kin_phone,
            'next_of_kin_email' => $this->next_of_kin_email,
            'next_of_kin_address' => $this->next_of_kin_address,
            'membership_type' => $this->membership_type,
            'kyc_status' => $this->kyc_status,
            'kyc_submitted_at' => $this->kyc_submitted_at,
            'kyc_verified_at' => $this->kyc_verified_at,
            'kyc_rejection_reason' => $this->kyc_rejection_reason,
            'kyc_documents' => $this->kyc_documents,
            'status' => $this->status,
            // Map user fields to top level for easier frontend access
            'first_name' => $this->user?->first_name ?? null,
            'last_name' => $this->user?->last_name ?? null,
            'email' => $this->user?->email ?? null,
            'phone' => $this->user?->phone ?? null,
            'user' => $this->whenLoaded('user', [
                'id' => $this->user->id,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'avatar_url' => $this->user->avatar_url,
                'role' => $this->user->role,
            ]),
            'wallet' => $this->when($this->user && $this->user->relationLoaded('wallet'), $this->user->wallet),
            'equity_wallet_balance' => $this->whenLoaded('equityWalletBalance', $this->equityWalletBalance),
            'loans' => $this->whenLoaded('loans'),
            'investments' => $this->whenLoaded('investments'),
            'contributions' => $this->whenLoaded('contributions'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
