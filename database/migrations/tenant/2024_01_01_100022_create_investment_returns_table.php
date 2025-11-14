<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('investment_id')->constrained('investments')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->date('return_date');
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('investment_id');
            $table->index('return_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_returns');
    }
};
