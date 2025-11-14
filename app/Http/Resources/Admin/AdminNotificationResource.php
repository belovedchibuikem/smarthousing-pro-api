<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->first_name . ' ' . $this->user->last_name,
                    'email' => $this->user->email,
                    'avatar_url' => $this->user->avatar_url,
                ];
            }),
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data ?? [],
            'read_at' => $this->read_at?->toISOString(),
            'is_read' => $this->isRead(),
            'is_unread' => $this->isUnread(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'time_ago' => $this->created_at?->diffForHumans(),
        ];
    }
}

