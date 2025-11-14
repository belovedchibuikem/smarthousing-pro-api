<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'member_id' => $this->member_id,
            'package_id' => $this->package_id,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'amount_paid' => $this->amount_paid,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'notes' => $this->notes,
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
            'business' => $this->whenLoaded('business', function () {
                return [
                    'id' => $this->business->id,
                    'name' => $this->business->name,
                    'slug' => $this->business->slug,
                ];
            }),
            'member' => $this->whenLoaded('member', function () {
                return [
                    'id' => $this->member->id,
                    'name' => $this->member->name,
                    'email' => $this->member->email,
                ];
            }),
            'package' => $this->whenLoaded('package', function () {
                return [
                    'id' => $this->package->id,
                    'name' => $this->package->name,
                    'price' => $this->package->price,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
