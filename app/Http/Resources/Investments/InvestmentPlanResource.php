<?php

namespace App\Http\Resources\Investments;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestmentPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'min_amount' => $this->min_amount,
            'max_amount' => $this->max_amount,
            'expected_return_rate' => $this->expected_return_rate,
            'min_duration_months' => $this->min_duration_months,
            'max_duration_months' => $this->max_duration_months,
            'return_type' => $this->return_type,
            'risk_level' => $this->risk_level,
            'risk_color' => $this->risk_color,
            'features' => $this->features,
            'terms_and_conditions' => $this->terms_and_conditions,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
