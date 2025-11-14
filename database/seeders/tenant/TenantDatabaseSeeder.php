<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
	public function run(): void
	{
		// Create default roles if Spatie\Permission\Models\Role is available
		// Create roles in tenant DB if permissions tables exist
		try {
			if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('roles')) {
				$roles = ['tenant-admin', 'member'];
				foreach ($roles as $roleName) {
					\Illuminate\Support\Facades\DB::connection('tenant')
						->table('roles')
						->updateOrInsert(
							['name' => $roleName, 'guard_name' => 'web'],
							['name' => $roleName, 'guard_name' => 'web']
						);
				}
			}
		} catch (\Throwable $e) {
			// ignore if permission tables are not installed
		}

		// Optionally seed default settings or landing page config if model exists
		if (class_exists(\App\Models\Tenant\LandingPageConfig::class)) {
			// Get tenant ID from database name or use a default
			$tenantId = $this->getTenantIdFromDatabase();
			
			\App\Models\Tenant\LandingPageConfig::firstOrCreate(
				['tenant_id' => $tenantId],
				[
					'is_published' => false,
					'sections' => [],
					'theme' => [
						'primary_color' => '#FDB11E',
						'secondary_color' => '#276254',
						'accent_color' => '#10b981',
						'font_family' => 'Inter',
					],
					'seo' => [
						'title' => 'Smart Housing',
						'description' => 'Multi-tenant housing management platform',
						'keywords' => 'housing, tenancy, wallet',
					],
				]
			);
		}

		// Seed default tenant content (properties, plans) if factories/models exist
		$this->call(\Database\Seeders\Tenant\DefaultTenantContentSeeder::class);
		$this->call(\Database\Seeders\Tenant\TenantDemoUsersSeeder::class);
	}
	
	private function getTenantIdFromDatabase(): string
	{
		$databaseName = \Illuminate\Support\Facades\Config::get('database.connections.tenant.database');
		
		// Extract tenant ID from database name (e.g., "frsc_housing_tenant_template" -> "frsc")
		if (preg_match('/^([^_]+)_housing_tenant_template$/', $databaseName, $matches)) {
			return $matches[1];
		}
		
		// Fallback to database name or default
		return $databaseName ?: 'default';
	}
}


