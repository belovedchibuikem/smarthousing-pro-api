<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateRolesTables extends Command
{
    protected $signature = 'create:roles-tables';
    protected $description = 'Create roles and permissions tables for all tenants';

    public function handle()
    {
        $this->info('Creating roles tables for all tenants...');

        // Get all tenants from stancl tenancy
        $tenantIds = ['test-tenant', 'demo-tenant'];

        foreach ($tenantIds as $tenantId) {
            $this->info("Creating roles tables for tenant: {$tenantId}");

            // Use tenant ID as database name (stancl tenancy convention)
            $databaseName = $tenantId . '_housing';
            
            // Configure tenant connection
            config(['database.connections.tenant.database' => $databaseName]);

            // Create roles table
            if (!Schema::connection('tenant')->hasTable('roles')) {
                DB::connection('tenant')->statement("
                    CREATE TABLE roles (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(125) NOT NULL,
                        guard_name VARCHAR(125) NOT NULL,
                        created_at TIMESTAMP NULL,
                        updated_at TIMESTAMP NULL,
                        UNIQUE KEY roles_name_guard_name_unique (name, guard_name)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            // Create permissions table
            if (!Schema::connection('tenant')->hasTable('permissions')) {
                DB::connection('tenant')->statement("
                    CREATE TABLE permissions (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(125) NOT NULL,
                        guard_name VARCHAR(125) NOT NULL,
                        created_at TIMESTAMP NULL,
                        updated_at TIMESTAMP NULL,
                        UNIQUE KEY permissions_name_guard_name_unique (name, guard_name)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            // Create model_has_roles table
            if (!Schema::connection('tenant')->hasTable('model_has_roles')) {
                DB::connection('tenant')->statement("
                    CREATE TABLE model_has_roles (
                        role_id BIGINT UNSIGNED NOT NULL,
                        model_type VARCHAR(255) NOT NULL,
                        model_id BIGINT UNSIGNED NOT NULL,
                        PRIMARY KEY (role_id, model_id, model_type),
                        INDEX model_has_roles_model_id_model_type_index (model_id, model_type),
                        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            // Create model_has_permissions table
            if (!Schema::connection('tenant')->hasTable('model_has_permissions')) {
                DB::connection('tenant')->statement("
                    CREATE TABLE model_has_permissions (
                        permission_id BIGINT UNSIGNED NOT NULL,
                        model_type VARCHAR(255) NOT NULL,
                        model_id BIGINT UNSIGNED NOT NULL,
                        PRIMARY KEY (permission_id, model_id, model_type),
                        INDEX model_has_permissions_model_id_model_type_index (model_id, model_type),
                        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            // Create role_has_permissions table
            if (!Schema::connection('tenant')->hasTable('role_has_permissions')) {
                DB::connection('tenant')->statement("
                    CREATE TABLE role_has_permissions (
                        permission_id BIGINT UNSIGNED NOT NULL,
                        role_id BIGINT UNSIGNED NOT NULL,
                        PRIMARY KEY (permission_id, role_id),
                        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
                        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            $this->info("✅ Roles tables created for tenant: {$tenantId}");
        }

        $this->info('✅ All roles tables created successfully!');
    }
}
