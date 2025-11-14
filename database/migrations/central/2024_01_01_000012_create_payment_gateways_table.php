<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_payment_gateways', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique(); // e.g., paystack, remita, stripe
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->jsonb('settings')->nullable(); // Gateway-specific settings
            $table->jsonb('supported_currencies')->nullable();
            $table->jsonb('supported_countries')->nullable();
            $table->decimal('transaction_fee_percentage', 5, 2)->default(0);
            $table->decimal('transaction_fee_fixed', 15, 2)->default(0);
            $table->decimal('minimum_amount', 15, 2)->default(0);
            $table->decimal('maximum_amount', 15, 2)->nullable();
            $table->decimal('platform_fee_percentage', 5, 2)->default(0);
            $table->decimal('platform_fee_fixed', 15, 2)->default(0);
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payment_gateways');
    }
};
