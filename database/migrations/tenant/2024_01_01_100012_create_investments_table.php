<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('members')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['savings', 'fixed_deposit', 'treasury_bills', 'bonds', 'stocks']);
            $table->integer('duration_months');
            $table->decimal('expected_return_rate', 5, 2);
            $table->enum('status', ['pending', 'active', 'rejected', 'completed'])->default('pending');
            $table->date('investment_date');
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->timestamps();
            
            $table->index('member_id');
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
