<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add payment_method if it doesn't exist
            if (!Schema::hasColumn('subscriptions', 'payment_method')) {
                $table->enum('payment_method', ['manual', 'paystack', 'remita', 'stripe', 'wallet'])->nullable()->after('payment_reference');
            }
            
            // Add next_billing_date if it doesn't exist
            if (!Schema::hasColumn('subscriptions', 'next_billing_date')) {
                $table->timestamp('next_billing_date')->nullable()->after('ends_at');
            }
            
            // Add current_period_start and current_period_end if they don't exist
            if (!Schema::hasColumn('subscriptions', 'current_period_start')) {
                $table->timestamp('current_period_start')->nullable()->after('starts_at');
            }
            
            if (!Schema::hasColumn('subscriptions', 'current_period_end')) {
                $table->timestamp('current_period_end')->nullable()->after('current_period_start');
            }
            
            // Add index for next_billing_date
            if (Schema::hasColumn('subscriptions', 'next_billing_date')) {
                $table->index('next_billing_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
            
            if (Schema::hasColumn('subscriptions', 'next_billing_date')) {
                $table->dropIndex(['next_billing_date']);
                $table->dropColumn('next_billing_date');
            }
            
            if (Schema::hasColumn('subscriptions', 'current_period_start')) {
                $table->dropColumn('current_period_start');
            }
            
            if (Schema::hasColumn('subscriptions', 'current_period_end')) {
                $table->dropColumn('current_period_end');
            }
        });
    }
};

