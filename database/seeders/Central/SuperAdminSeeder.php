<?php

namespace Database\Seeders\Central;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (!class_exists(\App\Models\Central\SuperAdmin::class)) {
            return;
        }

        // Create super-admin user
        $superAdmin = \App\Models\Central\SuperAdmin::updateOrCreate(
            ['email' => 'superadmin@smarthousing.test'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make('Password123!'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        // Assign Super Admin role to the user
        $superAdminRole = \App\Models\Central\Role::where('slug', 'super-admin')->first();
        if ($superAdminRole) {
            $superAdmin->assignRole($superAdminRole);
        }
    }
}


