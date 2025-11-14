<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('min_amount', 15, 2);
            $table->decimal('max_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2); // e.g., 10.50 for 10.5%
            $table->integer('min_tenure_months');
            $table->integer('max_tenure_months');
            $table->enum('interest_type', ['simple', 'compound'])->default('simple');
            $table->json('eligibility_criteria')->nullable();
            $table->json('required_documents')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('processing_fee_percentage')->default(0);
            $table->decimal('late_payment_fee', 15, 2)->default(0);
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('min_amount');
            $table->index('max_amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
