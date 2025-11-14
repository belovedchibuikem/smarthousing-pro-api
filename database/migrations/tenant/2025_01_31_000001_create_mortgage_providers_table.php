<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('mortgage_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->decimal('interest_rate_min', 5, 2)->nullable();
            $table->decimal('interest_rate_max', 5, 2)->nullable();
            $table->decimal('min_loan_amount', 15, 2)->nullable();
            $table->decimal('max_loan_amount', 15, 2)->nullable();
            $table->integer('min_tenure_years')->nullable();
            $table->integer('max_tenure_years')->nullable();
            $table->json('requirements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('mortgage_providers');
    }
};

