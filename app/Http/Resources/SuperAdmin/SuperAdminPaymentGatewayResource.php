<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuperAdminPaymentGatewayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            'supported_currencies' => $this->supported_currencies,
            'supported_countries' => $this->supported_countries,
            'transaction_fee_percentage' => $this->transaction_fee_percentage,
            'transaction_fee_fixed' => $this->transaction_fee_fixed,
            'minimum_amount' => $this->minimum_amount,
            'maximum_amount' => $this->maximum_amount,
            'platform_fee_percentage' => $this->platform_fee_percentage ?? 0,
            'platform_fee_fixed' => $this->platform_fee_fixed ?? 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
