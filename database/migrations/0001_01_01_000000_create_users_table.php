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
        // Central database should NOT have users table
        // Users belong to tenant databases only
        // This migration is completely disabled for multi-tenant architecture
        return;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is disabled - no tables to drop
        return;
    }
};
