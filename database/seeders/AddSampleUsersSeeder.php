<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Facades\Tenancy;
use Illuminate\Support\Facades\DB;

class AddSampleUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "Starting to add sample users to tenant databases...\n";
        
        // Get all tenants
        $tenants = Tenant::all();
        echo "Found " . $tenants->count() . " tenants\n";
        
        foreach ($tenants as $tenant) {
            echo "Processing tenant: {$tenant->id}\n";
            
            try {
                // Initialize tenant context
                Tenancy::initialize($tenant);
                
                // Check if users table exists, if not create it
                if (!DB::connection('tenant')->getSchemaBuilder()->hasTable('users')) {
                    echo "Users table does not exist for tenant {$tenant->id}, creating it...\n";
                    $this->createUsersTable();
                }
                
                // Add sample users
                $this->addSampleUsers($tenant);
                
                echo "Successfully added users for tenant: {$tenant->id}\n";
                
            } catch (\Exception $e) {
                echo "Error processing tenant {$tenant->id}: " . $e->getMessage() . "\n";
            } finally {
                Tenancy::end();
            }
        }
        
        echo "Sample users creation completed!\n";
    }
    
    private function createUsersTable()
    {
        DB::connection('tenant')->statement('
            CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(255) NOT NULL,
                last_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(255) DEFAULT "member",
                status VARCHAR(255) DEFAULT "active",
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL
            )
        ');
    }
    
    private function addSampleUsers($tenant)
    {
        // Create 2-5 random users for each tenant
        $userCount = rand(2, 5);
        
        for ($i = 1; $i <= $userCount; $i++) {
            try {
                User::create([
                    'first_name' => "User{$i}",
                    'last_name' => "Test",
                    'email' => "user{$i}@{$tenant->id}.test",
                    'password' => Hash::make('password'),
                    'role' => 'member',
                    'status' => 'active',
                    'created_at' => now()->subDays(rand(1, 30))
                ]);
                echo "Created user{$i}@{$tenant->id}.test\n";
            } catch (\Exception $e) {
                echo "Failed to create user{$i} for tenant {$tenant->id}: " . $e->getMessage() . "\n";
            }
        }
    }
}

