<?php

namespace App\Http\Resources\Loans;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'product_id' => $this->product_id,
            'amount' => $this->amount,
            'interest_rate' => $this->interest_rate,
            'duration_months' => $this->duration_months,
            'type' => $this->type,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'application_date' => $this->application_date,
            'monthly_payment' => $this->monthly_payment,
            'total_amount' => $this->total_amount,
            'interest_amount' => $this->interest_amount,
            'processing_fee' => $this->processing_fee,
            'required_documents' => $this->required_documents,
            'application_metadata' => $this->application_metadata,
            'approved_at' => $this->approved_at,
            'approved_by' => $this->approved_by,
            'rejection_reason' => $this->rejection_reason,
            'rejected_at' => $this->rejected_at,
            'rejected_by' => $this->rejected_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'member' => $this->whenLoaded('member', function () {
                return [
                    'id' => $this->member->id,
                    'member_number' => $this->member->member_number,
                    'user' => $this->whenLoaded('user', function () {
                        return [
                            'id' => $this->member->user->id,
                            'name' => $this->member->user->first_name . ' ' . $this->member->user->last_name,
                            'email' => $this->member->user->email,
                        ];
                    }),
                ];
            }),
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'description' => $this->product->description,
                    'interest_rate' => $this->product->interest_rate,
                    'min_amount' => $this->product->min_amount,
                    'max_amount' => $this->product->max_amount,
                    'min_tenure_months' => $this->product->min_tenure_months,
                    'max_tenure_months' => $this->product->max_tenure_months,
                ];
            }),
        ];
    }
}
