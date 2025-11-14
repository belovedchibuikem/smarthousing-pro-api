<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_label_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('brand_name');
            $table->string('logo_url')->nullable();
            $table->string('primary_color', 7)->default('#FDB11E');
            $table->string('secondary_color', 7)->default('#276254');
            $table->string('accent_color', 7)->default('#10b981');
            $table->string('font_family')->default('Inter');
            $table->text('custom_css')->nullable();
            $table->text('custom_js')->nullable();
            $table->string('favicon_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_label_settings');
    }
};
