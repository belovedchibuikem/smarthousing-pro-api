<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            // Add payment_status if it doesn't exist
            if (!Schema::hasColumn('member_subscriptions', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'completed', 'failed', 'rejected'])->default('pending')->after('payment_method');
            }
            
            // Add approved_by if it doesn't exist
            if (!Schema::hasColumn('member_subscriptions', 'approved_by')) {
                $table->uuid('approved_by')->nullable()->after('payment_status');
            }
            
            // Add approved_at if it doesn't exist
            if (!Schema::hasColumn('member_subscriptions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            
            // Add rejection_reason if it doesn't exist
            if (!Schema::hasColumn('member_subscriptions', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_at');
            }
            
            // Add payer_name if it doesn't exist
            if (!Schema::hasColumn('member_subscriptions', 'payer_name')) {
                $table->string('payer_name')->nullable()->after('rejection_reason');
            }
            
            // Add payer_phone if it doesn't exist
            if (!Schema::hasColumn('member_subscriptions', 'payer_phone')) {
                $table->string('payer_phone')->nullable()->after('payer_name');
            }
            
            // Add account_details if it doesn't exist
            if (!Schema::hasColumn('member_subscriptions', 'account_details')) {
                $table->text('account_details')->nullable()->after('payer_phone');
            }
            
            // Add payment_evidence if it doesn't exist
            if (!Schema::hasColumn('member_subscriptions', 'payment_evidence')) {
                $table->json('payment_evidence')->nullable()->after('account_details');
            }
            
            // Add next_billing_date if it doesn't exist
            if (!Schema::hasColumn('member_subscriptions', 'next_billing_date')) {
                $table->date('next_billing_date')->nullable()->after('end_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $columnsToDrop = [];
            
            if (Schema::hasColumn('member_subscriptions', 'payment_status')) {
                $columnsToDrop[] = 'payment_status';
            }
            if (Schema::hasColumn('member_subscriptions', 'approved_by')) {
                $columnsToDrop[] = 'approved_by';
            }
            if (Schema::hasColumn('member_subscriptions', 'approved_at')) {
                $columnsToDrop[] = 'approved_at';
            }
            if (Schema::hasColumn('member_subscriptions', 'rejection_reason')) {
                $columnsToDrop[] = 'rejection_reason';
            }
            if (Schema::hasColumn('member_subscriptions', 'payer_name')) {
                $columnsToDrop[] = 'payer_name';
            }
            if (Schema::hasColumn('member_subscriptions', 'payer_phone')) {
                $columnsToDrop[] = 'payer_phone';
            }
            if (Schema::hasColumn('member_subscriptions', 'account_details')) {
                $columnsToDrop[] = 'account_details';
            }
            if (Schema::hasColumn('member_subscriptions', 'payment_evidence')) {
                $columnsToDrop[] = 'payment_evidence';
            }
            if (Schema::hasColumn('member_subscriptions', 'next_billing_date')) {
                $columnsToDrop[] = 'next_billing_date';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};

