<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('mortgages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('member_id');
            $table->uuid('provider_id')->nullable();
            $table->uuid('property_id')->nullable();
            $table->decimal('loan_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->integer('tenure_years');
            $table->decimal('monthly_payment', 15, 2);
            $table->string('status')->default('pending'); // pending, approved, rejected, active, completed
            $table->date('application_date');
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('provider_id')->references('id')->on('mortgage_providers')->onDelete('set null');
            $table->foreign('property_id')->references('id')->on('properties')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('mortgages');
    }
};

