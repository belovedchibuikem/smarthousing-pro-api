<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Central\SuperAdmin::create([
            'email' => 'superadmin@test.com',
            'password' => 'password123',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'role' => 'super_admin',
            'is_active' => true
        ]);
        
        echo "Super Admin created successfully!\n";
    }
}
