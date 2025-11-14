<?php

namespace App\Http\Resources\Properties;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyManagementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'location' => $this->location,
            'price' => $this->price,
            'size' => $this->size,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'features' => $this->features,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->url ?? $image->image_url,
                        'caption' => $image->caption,
                        'is_primary' => $image->is_primary,
                    ];
                });
            }),
            'allocations' => $this->whenLoaded('allocations', function () {
                return $this->allocations->map(function ($allocation) {
                    return [
                        'id' => $allocation->id,
                        'member_id' => $allocation->member_id,
                        'allocation_date' => $allocation->allocation_date,
                        'status' => $allocation->status,
                        'notes' => $allocation->notes,
                        'member' => $this->whenLoaded('member', function () use ($allocation) {
                            return [
                                'id' => $allocation->member->id,
                                'member_number' => $allocation->member->member_number,
                                'user' => $this->whenLoaded('user', function () use ($allocation) {
                                    return [
                                        'id' => $allocation->member->user->id,
                                        'name' => $allocation->member->user->first_name . ' ' . $allocation->member->user->last_name,
                                        'email' => $allocation->member->user->email,
                                    ];
                                }),
                            ];
                        }),
                    ];
                });
            }),
        ];
    }
}
