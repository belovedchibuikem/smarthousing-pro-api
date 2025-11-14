<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentGatewayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Map gateway_type to display-friendly names
        $displayNames = [
            'paystack' => 'Paystack',
            'remita' => 'Remita',
            'stripe' => 'Stripe',
            'manual' => 'Manual Payment',
        ];
        
        $descriptions = [
            'paystack' => 'Accept payments via Paystack',
            'remita' => 'Accept payments via Remita',
            'stripe' => 'Accept payments via Stripe',
            'manual' => 'Manual payment processing',
        ];
        
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->gateway_type, // Frontend uses 'name'
            'gateway_type' => $this->gateway_type, // Keep for backward compatibility
            'display_name' => $displayNames[$this->gateway_type] ?? ucfirst($this->gateway_type),
            'description' => $descriptions[$this->gateway_type] ?? 'Payment gateway',
            'is_active' => $this->is_enabled, // Map is_enabled to is_active for frontend
            'is_enabled' => $this->is_enabled, // Keep for backward compatibility
            'is_test_mode' => $this->is_test_mode,
            'settings' => $this->credentials ?? [], // Map credentials to settings for frontend
            'credentials' => $this->credentials ?? [], // Keep for backward compatibility
            'configuration' => $this->configuration,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
