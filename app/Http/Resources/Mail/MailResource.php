<?php

namespace App\Http\Resources\Mail;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'priority' => $this->priority,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at,
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->first_name . ' ' . $this->sender->last_name,
                'email' => $this->sender->email,
            ],
            'recipient' => [
                'id' => $this->recipient->id,
                'name' => $this->recipient->first_name . ' ' . $this->recipient->last_name,
                'email' => $this->recipient->email,
            ],
            'replies' => $this->whenLoaded('replies', function () {
                return MailResource::collection($this->replies);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
