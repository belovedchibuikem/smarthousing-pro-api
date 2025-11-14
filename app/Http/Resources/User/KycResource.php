<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KycResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'member_id' => $this->id,
            'status' => $this->kyc_status ?? $this->status ?? 'pending',
            'submitted_at' => $this->kyc_submitted_at ?? $this->submitted_at,
            'verified_at' => $this->kyc_verified_at ?? $this->reviewed_at ?? null,
            'rejection_reason' => $this->kyc_rejection_reason ?? $this->rejection_reason ?? null,
            'documents' => $this->kyc_documents ?? $this->documents ?? [],
            'next_of_kin' => [
                'name' => $this->next_of_kin_name ?? null,
                'relationship' => $this->next_of_kin_relationship ?? null,
                'phone' => $this->next_of_kin_phone ?? null,
                'email' => $this->next_of_kin_email ?? null,
                'address' => $this->next_of_kin_address ?? null,
            ],
            'required_documents' => [
                'passport',
                'national_id',
                'drivers_license',
                'utility_bill',
                'bank_statement',
            ],
        ];
    }
}