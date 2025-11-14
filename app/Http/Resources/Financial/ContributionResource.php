<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'amount' => $this->amount,
            'type' => $this->type,
            'frequency' => $this->frequency,
            'status' => $this->status,
            'contribution_date' => $this->contribution_date,
            'approved_at' => $this->approved_at,
            'approved_by' => $this->approved_by,
            'rejection_reason' => $this->rejection_reason,
            'rejected_at' => $this->rejected_at,
            'rejected_by' => $this->rejected_by,
            'total_paid' => $this->total_paid,
            'remaining_amount' => $this->remaining_amount,
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
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'payment_date' => $payment->payment_date,
                        'status' => $payment->status,
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
