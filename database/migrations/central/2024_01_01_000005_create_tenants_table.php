<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->json('data');
            $table->timestamps();
        });
        
        // Create additional tenant details table
        Schema::create('tenant_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('slug')->unique(); // Used for subdomain
            $table->string('custom_domain')->unique()->nullable();
            $table->string('logo_url')->nullable();
            $table->string('primary_color', 7)->default('#FDB11E');
            $table->string('secondary_color', 7)->default('#276254');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
            $table->enum('subscription_status', ['trial', 'active', 'past_due', 'cancelled'])->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('slug');
            $table->index('custom_domain');
            $table->index('status');
            $table->index('subscription_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_details');
        Schema::dropIfExists('tenants');
    }
};
