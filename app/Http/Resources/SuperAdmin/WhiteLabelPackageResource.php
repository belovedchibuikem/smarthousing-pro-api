<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhiteLabelPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'billing_cycle' => $this->billing_cycle,
            'features' => $this->features,
            'is_active' => $this->is_active,
            'subscribers' => $this->subscribers ?? 0, // You might want to add this relationship
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}



