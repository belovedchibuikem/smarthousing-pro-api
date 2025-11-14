<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('package_id')->constrained('packages')->onDelete('cascade');
            $table->foreignUuid('module_id')->constrained('modules')->onDelete('cascade');
            $table->json('limits')->nullable(); // Module-specific limits
            $table->timestamps();
            
            $table->unique(['package_id', 'module_id']);
            $table->index('package_id');
            $table->index('module_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_modules');
    }
};
