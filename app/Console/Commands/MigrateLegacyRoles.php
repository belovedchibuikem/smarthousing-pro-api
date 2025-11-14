<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\MigrateLegacyRolesToSpatieSeeder;

class MigrateLegacyRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:migrate-legacy-roles {--tenant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy user roles to Spatie Permission system for a specific tenant';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenant = $this->option('tenant');
        
        if (!$tenant) {
            $this->error('Please specify a tenant using --tenant=tenant_name');
            return 1;
        }

        $this->info("Migrating legacy roles for tenant: {$tenant}");

        // Set the tenant context
        $tenantModel = \App\Models\Central\Tenant::where('domain', $tenant)->first();
        
        if (!$tenantModel) {
            $this->error("Tenant '{$tenant}' not found");
            return 1;
        }

        // Set tenant context
        tenancy()->initialize($tenantModel);

        try {
            // Run the seeder
            $seeder = new MigrateLegacyRolesToSpatieSeeder();
            $seeder->setCommand($this);
            $seeder->run();

            $this->info('Legacy role migration completed successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return 1;
        } finally {
            tenancy()->end();
        }
    }
}
