<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('min_amount', 15, 2);
            $table->decimal('max_amount', 15, 2);
            $table->decimal('expected_return_rate', 5, 2); // e.g., 12.50 for 12.5%
            $table->integer('min_duration_months');
            $table->integer('max_duration_months');
            $table->enum('return_type', ['fixed', 'variable'])->default('fixed');
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('medium');
            $table->json('features')->nullable();
            $table->json('terms_and_conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('risk_level');
            $table->index('min_amount');
            $table->index('max_amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_plans');
    }
};
