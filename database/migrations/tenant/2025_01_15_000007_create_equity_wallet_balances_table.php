<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equity_wallet_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->unique()->constrained('members')->onDelete('cascade');
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('total_contributed', 15, 2)->default(0);
            $table->decimal('total_used', 15, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
            
            $table->index('member_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equity_wallet_balances');
    }
};

