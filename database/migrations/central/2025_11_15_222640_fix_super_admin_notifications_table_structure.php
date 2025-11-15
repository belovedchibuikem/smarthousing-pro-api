<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if table exists
        if (Schema::hasTable('super_admin_notifications')) {
            // Check if id column is bigint (wrong) or uuid (correct)
            $columns = DB::select("SHOW COLUMNS FROM super_admin_notifications WHERE Field = 'id'");
            
            if (!empty($columns)) {
                $idColumn = $columns[0];
                
                // If id is bigint, we need to drop and recreate the table
                // Otherwise, just add missing columns
                if (strpos($idColumn->Type, 'bigint') !== false || strpos($idColumn->Type, 'int') !== false) {
                    // Drop and recreate with correct structure
                    Schema::dropIfExists('super_admin_notifications');
                    Schema::create('super_admin_notifications', function (Blueprint $table) {
                        $table->uuid('id')->primary();
                        $table->foreignUuid('super_admin_id')->constrained('super_admins')->onDelete('cascade');
                        $table->enum('type', ['info', 'success', 'warning', 'error', 'system'])->default('info');
                        $table->string('title');
                        $table->text('message');
                        $table->json('data')->nullable();
                        $table->timestamp('read_at')->nullable();
                        $table->timestamps();
                        
                        $table->index('super_admin_id');
                        $table->index('type');
                        $table->index('read_at');
                        $table->index('created_at');
                    });
                } else {
                    // Table has UUID id, just add missing columns if they don't exist
                    Schema::table('super_admin_notifications', function (Blueprint $table) {
                        if (!Schema::hasColumn('super_admin_notifications', 'super_admin_id')) {
                            $table->foreignUuid('super_admin_id')->after('id')->constrained('super_admins')->onDelete('cascade');
                        }
                        if (!Schema::hasColumn('super_admin_notifications', 'type')) {
                            $table->enum('type', ['info', 'success', 'warning', 'error', 'system'])->default('info')->after('super_admin_id');
                        }
                        if (!Schema::hasColumn('super_admin_notifications', 'title')) {
                            $table->string('title')->after('type');
                        }
                        if (!Schema::hasColumn('super_admin_notifications', 'message')) {
                            $table->text('message')->after('title');
                        }
                        if (!Schema::hasColumn('super_admin_notifications', 'data')) {
                            $table->json('data')->nullable()->after('message');
                        }
                        if (!Schema::hasColumn('super_admin_notifications', 'read_at')) {
                            $table->timestamp('read_at')->nullable()->after('data');
                        }
                    });
                    
                    // Add indexes if they don't exist
                    $indexes = DB::select("SHOW INDEXES FROM super_admin_notifications");
                    $indexNames = array_column($indexes, 'Key_name');
                    
                    Schema::table('super_admin_notifications', function (Blueprint $table) use ($indexNames) {
                        if (!in_array('super_admin_notifications_super_admin_id_index', $indexNames)) {
                            $table->index('super_admin_id');
                        }
                        if (!in_array('super_admin_notifications_type_index', $indexNames)) {
                            $table->index('type');
                        }
                        if (!in_array('super_admin_notifications_read_at_index', $indexNames)) {
                            $table->index('read_at');
                        }
                        if (!in_array('super_admin_notifications_created_at_index', $indexNames)) {
                            $table->index('created_at');
                        }
                    });
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration fixes the structure, so we don't need to reverse it
        // The original incomplete migration will handle the down if needed
    }
};
