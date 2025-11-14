<?php

namespace Database\Seeders\Central;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DomainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default domains for development
        $domains = [
            // FRSC domains
            [
                'domain' => 'localhost',
                'tenant_id' => 'frsc',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'domain' => '127.0.0.1',
                'tenant_id' => 'frsc',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'domain' => 'localhost:8000',
                'tenant_id' => 'frsc',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'domain' => '127.0.0.1:8000',
                'tenant_id' => 'frsc',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ACME domains
            [
                'domain' => 'acme.localhost',
                'tenant_id' => 'acme',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'domain' => 'acme.localhost:8000',
                'tenant_id' => 'acme',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($domains as $domainData) {
            DB::table('domains')->updateOrInsert(
                ['domain' => $domainData['domain']],
                $domainData
            );
        }

        $this->command->info('✅ Domains seeded successfully!');
    }
}
