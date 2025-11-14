<?php

namespace App\Http\Resources\Properties;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'member_id' => $this->member_id,
            'allocation_date' => $this->allocation_date,
            'status' => $this->status,
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'property' => $this->whenLoaded('property', function () {
                return [
                    'id' => $this->property->id,
                    'title' => $this->property->title,
                    'type' => $this->property->type,
                    'location' => $this->property->location,
                    'price' => $this->property->price,
                ];
            }),
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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
