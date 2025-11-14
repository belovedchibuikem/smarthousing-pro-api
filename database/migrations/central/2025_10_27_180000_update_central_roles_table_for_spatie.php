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
        Schema::table('roles', function (Blueprint $table) {
            // Add missing columns to match Spatie structure
            $table->string('color')->nullable()->after('description');
            $table->integer('sort_order')->default(0)->after('color');
            
            // Update existing columns to match Spatie structure
            $table->string('name', 125)->change();
            $table->string('guard_name', 125)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['color', 'sort_order']);
        });
    }
};


