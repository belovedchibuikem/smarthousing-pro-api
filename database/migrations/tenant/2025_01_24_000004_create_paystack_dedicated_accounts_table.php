<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paystack_dedicated_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->unique('user_id');
            $table->string('customer_code')->index();
            $table->string('customer_id')->nullable();
            $table->string('dedicated_account_id')->nullable()->index();
            $table->string('account_number')->unique();
            $table->string('account_name');
            $table->string('bank_name')->nullable();
            $table->string('bank_slug')->nullable();
            $table->string('currency', 10)->default('NGN');
            $table->string('status')->default('active');
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paystack_dedicated_accounts');
    }
};

