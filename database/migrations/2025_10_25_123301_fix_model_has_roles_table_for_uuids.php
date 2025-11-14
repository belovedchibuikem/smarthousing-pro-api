<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Handle model_has_roles table
        if (Schema::hasTable('model_has_roles')) {
            // Backup existing data
            $existingData = DB::table('model_has_roles')->get();
            
            // Drop the existing table
            Schema::dropIfExists('model_has_roles');
        } else {
            $existingData = collect();
        }

        // Create model_has_roles table with UUID support
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(Str::uuid());
            $table->uuid('role_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->timestamps();

            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->index(['role_id']);

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');
        });

        // Restore data if it existed
        if ($existingData->isNotEmpty()) {
            foreach ($existingData as $row) {
                DB::table('model_has_roles')->insert([
                    'id' => Str::uuid(),
                    'role_id' => $row->role_id,
                    'model_type' => $row->model_type,
                    'model_id' => $row->model_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Handle model_has_permissions table
        if (Schema::hasTable('model_has_permissions')) {
            $existingPermissionsData = DB::table('model_has_permissions')->get();
            Schema::dropIfExists('model_has_permissions');
        } else {
            $existingPermissionsData = collect();
        }

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(Str::uuid());
            $table->uuid('permission_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->timestamps();

            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->index(['permission_id']);

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');
        });

        // Restore permissions data if it existed
        if ($existingPermissionsData->isNotEmpty()) {
            foreach ($existingPermissionsData as $row) {
                DB::table('model_has_permissions')->insert([
                    'id' => Str::uuid(),
                    'permission_id' => $row->permission_id,
                    'model_type' => $row->model_type,
                    'model_id' => $row->model_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Handle role_has_permissions table
        if (Schema::hasTable('role_has_permissions')) {
            $existingRolePermissionsData = DB::table('role_has_permissions')->get();
            Schema::dropIfExists('role_has_permissions');
        } else {
            $existingRolePermissionsData = collect();
        }

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(Str::uuid());
            $table->uuid('permission_id');
            $table->uuid('role_id');
            $table->timestamps();

            $table->index(['permission_id']);
            $table->index(['role_id']);

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');
        });

        // Restore role permissions data if it existed
        if ($existingRolePermissionsData->isNotEmpty()) {
            foreach ($existingRolePermissionsData as $row) {
                DB::table('role_has_permissions')->insert([
                    'id' => Str::uuid(),
                    'permission_id' => $row->permission_id,
                    'role_id' => $row->role_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
    }
};