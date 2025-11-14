<?php

namespace App\Http\Resources\Loans;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'min_amount' => $this->min_amount,
            'max_amount' => $this->max_amount,
            'interest_rate' => $this->interest_rate,
            'min_tenure_months' => $this->min_tenure_months,
            'max_tenure_months' => $this->max_tenure_months,
            'interest_type' => $this->interest_type,
            'eligibility_criteria' => $this->eligibility_criteria,
            'required_documents' => $this->required_documents,
            'processing_fee_percentage' => $this->processing_fee_percentage,
            'late_payment_fee' => $this->late_payment_fee,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
