<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_domain_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('domain');
            $table->enum('status', ['pending', 'verifying', 'verified', 'active', 'failed', 'rejected'])->default('pending');
            $table->string('verification_token')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->json('dns_records')->nullable();
            $table->boolean('ssl_enabled')->default(false);
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('domain');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_domain_requests');
    }
};
