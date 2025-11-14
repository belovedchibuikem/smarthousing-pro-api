<?php

namespace Database\Seeders\Central;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SuperAdminSeeder::class,
            MemberSubscriptionPackageSeeder::class,
            TenantSeeder::class,
        ]);
    }
}


