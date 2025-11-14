<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equity_contributions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignUuid('plan_id')->nullable()->constrained('equity_plans')->onDelete('set null');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['paystack', 'remita', 'stripe', 'manual', 'bank_transfer'])->default('manual');
            $table->enum('status', ['pending', 'approved', 'rejected', 'failed'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->string('transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('payment_metadata')->nullable();
            $table->timestamps();
            
            $table->index('member_id');
            $table->index('plan_id');
            $table->index('status');
            $table->index('payment_method');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equity_contributions');
    }
};

