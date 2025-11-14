<?php

namespace Database\Seeders\Central;

use Illuminate\Database\Seeder;

class CentralDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PackageSeeder::class,
            SuperAdminSeeder::class,
            TenantSeeder::class,
            DomainSeeder::class,
        ]);
    }
}


