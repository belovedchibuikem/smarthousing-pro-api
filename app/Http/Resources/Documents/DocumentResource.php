<?php

namespace App\Http\Resources\Documents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'file_size' => $this->file_size,
            'file_size_human' => $this->file_size_human,
            'mime_type' => $this->mime_type,
            'status' => $this->status,
            'approved_at' => $this->approved_at,
            'rejected_at' => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,
            'member' => [
                'id' => $this->member->id,
                'member_number' => $this->member->member_number,
                'user' => [
                    'id' => $this->member->user->id,
                    'first_name' => $this->member->user->first_name,
                    'last_name' => $this->member->user->last_name,
                    'email' => $this->member->user->email,
                ]
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
