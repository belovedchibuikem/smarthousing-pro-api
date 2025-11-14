<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Facades\Tenancy;

class SampleUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all tenants
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            try {
                // Initialize tenant context
                Tenancy::initialize($tenant);
                
                // Create sample users for this tenant
                $this->createSampleUsers($tenant);
                
                echo "Created sample users for tenant: {$tenant->id}\n";
                
            } catch (\Exception $e) {
                echo "Failed to create users for tenant {$tenant->id}: " . $e->getMessage() . "\n";
            } finally {
                Tenancy::end();
            }
        }
        
        echo "Sample users created successfully!\n";
    }
    
    private function createSampleUsers($tenant)
    {
        // Create 5-15 random users for each tenant
        $userCount = rand(5, 15);
        
        for ($i = 1; $i <= $userCount; $i++) {
            User::firstOrCreate([
                'email' => "user{$i}@{$tenant->id}.test"
            ], [
                'first_name' => "User{$i}",
                'last_name' => "Test",
                'password' => Hash::make('password'),
                'role' => 'member',
                'status' => 'active',
                'created_at' => now()->subDays(rand(1, 30))
            ]);
        }
    }
}

