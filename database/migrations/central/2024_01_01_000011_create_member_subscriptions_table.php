<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('tenants')->onDelete('cascade');
            $table->uuid('member_id'); // References member in tenant database
            $table->string('package_id'); // Package identifier
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('amount_paid', 15, 2);
            $table->enum('payment_method', ['manual', 'paystack', 'remita', 'wallet']);
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            
            $table->index('business_id');
            $table->index('member_id');
            $table->index('package_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_subscriptions');
    }
};
