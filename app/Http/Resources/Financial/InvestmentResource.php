<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'amount' => $this->amount,
            'type' => $this->type,
            'duration_months' => $this->duration_months,
            'expected_return_rate' => $this->expected_return_rate,
            'status' => $this->status,
            'investment_date' => $this->investment_date,
            'approved_at' => $this->approved_at,
            'approved_by' => $this->approved_by,
            'rejection_reason' => $this->rejection_reason,
            'rejected_at' => $this->rejected_at,
            'rejected_by' => $this->rejected_by,
            'expected_return' => $this->expected_return,
            'total_return' => $this->total_return,
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
            'returns' => $this->whenLoaded('returns', function () {
                return $this->returns->map(function ($return) {
                    return [
                        'id' => $return->id,
                        'amount' => $return->amount,
                        'return_date' => $return->return_date,
                        'type' => $return->type,
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
