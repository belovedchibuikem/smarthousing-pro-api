<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuperAdminManagementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Get the role ID from the Spatie permissions
        $roleId = null;
        if ($this->roles()->count() > 0) {
            $roleId = $this->roles()->first()->id;
        }

        return [
            'id' => $this->id,
            'name' => $this->first_name . ' ' . $this->last_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'role' => $roleId ?: $this->role, // Return role ID if available, fallback to role name
            'status' => $this->is_active ? 'active' : 'inactive',
            'permissions' => $this->permissions,
            'last_login' => $this->last_login,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
