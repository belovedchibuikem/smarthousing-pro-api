<?php

namespace App\Http\Resources\Properties;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'location' => $this->location,
            'address' => $this->address,
            'price' => $this->price,
            'size' => $this->size,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'features' => $this->features,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'coordinates' => $this->coordinates,
            'interest_status' => $this->interest_status ?? null,
            'interest_id' => $this->interest_id ?? null,
            'interest_type' => $this->interest_type ?? null,
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->url,
                        'is_primary' => $image->is_primary,
                        'alt_text' => $image->alt_text,
                    ];
                });
            }),
            'primary_image' => $this->primary_image ? [
                'id' => $this->primary_image->id,
                'url' => $this->primary_image->url,
                'alt_text' => $this->primary_image->alt_text,
            ] : null,
            'allocations_count' => $this->whenLoaded('allocations', function () {
                return $this->allocations->count();
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
