<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'amount' => $this->amount,
            'interest_rate' => $this->interest_rate,
            'duration_months' => $this->duration_months,
            'type' => $this->type,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'application_date' => $this->application_date,
            'approved_at' => $this->approved_at,
            'approved_by' => $this->approved_by,
            'rejection_reason' => $this->rejection_reason,
            'rejected_at' => $this->rejected_at,
            'rejected_by' => $this->rejected_by,
            'total_amount' => $this->total_amount,
            'monthly_payment' => $this->monthly_payment,
            'member' => $this->whenLoaded('member', function () {
                return [
                    'id' => $this->member->id,
                    'member_number' => $this->member->member_number,
                    'full_name' => $this->member->full_name,
                    'user' => $this->when($this->member->relationLoaded('user'), function () {
                        return [
                            'id' => $this->member->user->id,
                            'email' => $this->member->user->email,
                            'phone' => $this->member->user->phone,
                        ];
                    }),
                ];
            }),
            'repayments' => $this->whenLoaded('repayments', function () {
                return $this->repayments->map(function ($repayment) {
                    return [
                        'id' => $repayment->id,
                        'amount' => $repayment->amount,
                        'due_date' => $repayment->due_date,
                        'status' => $repayment->status,
                        'paid_at' => $repayment->paid_at,
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
