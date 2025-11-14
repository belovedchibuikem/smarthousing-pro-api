<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->enum('gateway_type', ['paystack', 'remita', 'stripe', 'manual']);
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_test_mode')->default(true);
            $table->json('credentials');
            $table->json('configuration')->nullable();
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('gateway_type');
            $table->unique(['tenant_id', 'gateway_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
