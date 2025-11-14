<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equity_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignUuid('equity_wallet_balance_id')->constrained('equity_wallet_balances')->onDelete('cascade');
            $table->enum('type', ['contribution', 'deposit_payment', 'refund', 'adjustment'])->default('contribution');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->string('reference')->nullable(); // Can be property_id, contribution_id, etc.
            $table->string('reference_type')->nullable(); // 'property', 'contribution', etc.
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('member_id');
            $table->index('equity_wallet_balance_id');
            $table->index('type');
            $table->index('reference');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equity_transactions');
    }
};

