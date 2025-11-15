<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This migration is a duplicate - table should already exist from 2025_10_25_043748
        // Only create if it doesn't exist
        if (!Schema::hasTable('super_admin_notifications')) {
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
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_notifications');
    }
};
