<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->boolean('is_published')->default(false);
            $table->json('sections')->nullable();
            $table->json('theme')->nullable();
            $table->json('seo')->nullable();
            $table->timestamps();
            
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_configs');
    }
};
