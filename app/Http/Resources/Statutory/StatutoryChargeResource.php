<?php

namespace App\Http\Resources\Statutory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatutoryChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Calculate total_paid efficiently using loaded payments if available
        $totalPaid = 0;
        if ($this->relationLoaded('payments')) {
            $totalPaid = (float) $this->payments->sum('amount');
        } else {
            $totalPaid = (float) $this->total_paid;
        }
        
        $remainingAmount = max(0, (float) $this->amount - $totalPaid);
        
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'due_date' => $this->due_date?->toIso8601String(),
            'status' => $this->status,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'total_paid' => $totalPaid,
            'remaining_amount' => $remainingAmount,
            'is_overdue' => $this->is_overdue,
            'member' => $this->whenLoaded('member', function () {
                return [
                    'id' => $this->member->id,
                    'member_number' => $this->member->member_number,
                    'user' => $this->when($this->member->relationLoaded('user'), function () {
                        return [
                            'id' => $this->member->user->id,
                            'first_name' => $this->member->user->first_name,
                            'last_name' => $this->member->user->last_name,
                            'email' => $this->member->user->email,
                        ];
                    }),
                ];
            }),
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => (float) $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'reference' => $payment->reference,
                        'status' => $payment->status,
                        'paid_at' => $payment->paid_at?->toIso8601String(),
                        'created_at' => $payment->created_at?->toIso8601String(),
                    ];
                });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
