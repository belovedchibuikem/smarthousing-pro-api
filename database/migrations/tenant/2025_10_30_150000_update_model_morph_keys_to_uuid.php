<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        // Ensure Spatie tables exist before altering
        if (!isset($tableNames['model_has_roles'], $tableNames['model_has_permissions'])) {
            return;
        }

        // Convert model_id columns from integer to CHAR(36) to support UUID model keys
        // Use raw SQL to avoid requiring doctrine/dbal for column alterations
        $modelId = $columnNames['model_morph_key'] ?? 'model_id';

        // model_has_roles
        DB::statement("ALTER TABLE `{$tableNames['model_has_roles']}` MODIFY `{$modelId}` CHAR(36) NOT NULL");

        // model_has_permissions
        DB::statement("ALTER TABLE `{$tableNames['model_has_permissions']}` MODIFY `{$modelId}` CHAR(36) NOT NULL");
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        if (!isset($tableNames['model_has_roles'], $tableNames['model_has_permissions'])) {
            return;
        }

        // Revert back to unsignedBigInteger (original default) if needed
        $modelId = $columnNames['model_morph_key'] ?? 'model_id';

        DB::statement("ALTER TABLE `{$tableNames['model_has_roles']}` MODIFY `{$modelId}` BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE `{$tableNames['model_has_permissions']}` MODIFY `{$modelId}` BIGINT UNSIGNED NOT NULL");
    }
};


