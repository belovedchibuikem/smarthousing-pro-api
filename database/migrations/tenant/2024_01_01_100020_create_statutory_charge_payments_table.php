<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_charge_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('statutory_charge_id')->constrained('statutory_charges')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method'); // bank_transfer, card, cash, etc.
            $table->string('reference')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->index('statutory_charge_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_charge_payments');
    }
};
