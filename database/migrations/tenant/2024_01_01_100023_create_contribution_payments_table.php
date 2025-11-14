<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contribution_id')->constrained('contributions')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method'); // bank_transfer, card, cash, etc.
            $table->string('reference')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('contribution_id');
            $table->index('payment_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_payments');
    }
};
