<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('mortgage_repayments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('mortgage_id');
            $table->uuid('property_id')->nullable(); // Link to property if mortgage is tied to property
            $table->decimal('amount', 15, 2); // Total payment amount
            $table->decimal('principal_paid', 15, 2)->default(0); // Principal portion
            $table->decimal('interest_paid', 15, 2)->default(0); // Interest portion
            $table->date('due_date');
            $table->enum('status', ['pending', 'paid', 'overdue', 'partial'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable(); // monthly, yearly, bi-yearly, etc.
            $table->string('reference')->nullable();
            $table->uuid('recorded_by')->nullable(); // Admin who recorded the payment
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('mortgage_id')->references('id')->on('mortgages')->onDelete('cascade');
            $table->foreign('property_id')->references('id')->on('properties')->onDelete('set null');
            $table->index('mortgage_id');
            $table->index('due_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('mortgage_repayments');
    }
};

