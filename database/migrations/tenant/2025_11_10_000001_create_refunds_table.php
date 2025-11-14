<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('members')->onDelete('cascade');
            $table->enum('source', ['wallet', 'contribution', 'investment_return', 'equity_wallet']);
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->foreignUuid('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('member_id');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};

