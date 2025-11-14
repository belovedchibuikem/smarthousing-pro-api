<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_auto_pay_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('members')->onDelete('cascade');
            $table->boolean('is_enabled')->default(false);
            $table->string('payment_method')->default('wallet'); // wallet or card
            $table->decimal('amount', 15, 2)->nullable();
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->json('metadata')->nullable();
            $table->string('card_reference')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_auto_pay_settings');
    }
};

