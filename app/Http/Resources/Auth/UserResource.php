<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\Members\MemberResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'role' => $this->role,
            'roles' => [$this->role], // Convert single role to array for frontend compatibility
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at,
            'last_login' => $this->last_login,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'member' => $this->whenLoaded('member', fn () => new MemberResource($this->member)),
        ];
    }
}