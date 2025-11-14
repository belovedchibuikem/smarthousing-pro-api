<?php

namespace Database\Seeders\Central;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default tenants for development
        $tenants = [
            [
                'id' => 'frsc',
                'name' => 'FRSC Housing Cooperative',
                    'slug' => 'frsc',
                    'status' => 'active',
                    'subscription_status' => 'active',
                    'primary_color' => '#FDB11E',
                    'secondary_color' => '#276254',
                'contact_email' => 'info@frsc-housing.com',
                'contact_phone' => '+234 800 000 0000',
                    'address' => 'FRSC Headquarters, Abuja, Nigeria',
            ],
            [
                'id' => 'acme',
                    'name' => 'ACME Corporation',
                    'slug' => 'acme',
                    'status' => 'active',
                    'subscription_status' => 'trial',
                    'primary_color' => '#3B82F6',
                    'secondary_color' => '#1E40AF',
                    'contact_email' => 'admin@acme.com',
                    'contact_phone' => '+234-987-654-3210',
                    'address' => 'ACME Building, Lagos, Nigeria',
            ],
        ];

        foreach ($tenants as $tenantData) {
            // Create or update tenant in Stancl tenancy table
            $tenant = Tenant::updateOrCreate(
                ['id' => $tenantData['id']],
                [
                    'data' => [
                        'name' => $tenantData['name'],
                        'slug' => $tenantData['slug'],
                        'status' => $tenantData['status'],
                        'subscription_status' => $tenantData['subscription_status'],
                        'primary_color' => $tenantData['primary_color'],
                        'secondary_color' => $tenantData['secondary_color'],
                        'contact_email' => $tenantData['contact_email'],
                        'contact_phone' => $tenantData['contact_phone'],
                        'address' => $tenantData['address'],
                    ],
                ]
            );

            // Create or update tenant_details entry
            DB::connection('mysql')->table('tenant_details')->updateOrInsert(
                [
                    'tenant_id' => $tenant->id,
                    'slug' => $tenantData['slug'],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $tenantData['name'],
                    'slug' => $tenantData['slug'],
                    'custom_domain' => null,
                    'logo_url' => null,
                    'primary_color' => $tenantData['primary_color'],
                    'secondary_color' => $tenantData['secondary_color'],
                    'contact_email' => $tenantData['contact_email'],
                    'contact_phone' => $tenantData['contact_phone'],
                    'address' => $tenantData['address'],
                    'status' => $tenantData['status'],
                    'subscription_status' => $tenantData['subscription_status'],
                    'trial_ends_at' => $tenantData['subscription_status'] === 'trial' ? now()->addDays(14) : null,
                    'subscription_ends_at' => $tenantData['subscription_status'] === 'active' ? now()->addYear() : null,
                    'settings' => json_encode([
                        'timezone' => 'Africa/Lagos',
                        'currency' => 'NGN',
                    ]),
                    'metadata' => json_encode([]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->command->info("✅ Tenant '{$tenantData['slug']}' seeded successfully!");
        }

        $this->command->info('✅ All tenants seeded successfully!');
    }
}
