<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if package_id column exists and drop it if it does
        if (Schema::hasColumn('member_subscriptions', 'package_id')) {
            Schema::table('member_subscriptions', function (Blueprint $table) {
                // Drop foreign key constraint if it exists
                try {
                    $table->dropForeign(['package_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }
                $table->dropColumn('package_id');
            });
        }

        Schema::table('member_subscriptions', function (Blueprint $table) {
            // Add new package_id as foreign UUID
            $table->uuid('package_id')->after('member_id');
            
            // Add payment status for manual payments
            if (!Schema::hasColumn('member_subscriptions', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'approved', 'rejected', 'completed'])->default('completed')->after('payment_method');
            }
            
            // Add fields for manual payment approval
            if (!Schema::hasColumn('member_subscriptions', 'approved_by')) {
                $table->uuid('approved_by')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('member_subscriptions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('member_subscriptions', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_at');
            }
        });

        // Add foreign key constraint after columns are created
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->foreign('package_id')
                ->references('id')
                ->on('member_subscription_packages')
                ->onDelete('restrict');
            
            // Add stripe support - update enum if needed
            if (Schema::hasColumn('member_subscriptions', 'payment_method')) {
                // Note: Laravel doesn't support changing enum values easily
                // This might require manual DB update in production
                // For now, we'll add a comment about this
            }
            
            // Add index for payment status if column exists
            if (Schema::hasColumn('member_subscriptions', 'payment_status')) {
                $table->index('payment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropColumn(['package_id', 'payment_status', 'approved_by', 'approved_at', 'rejection_reason']);
        });

        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->string('package_id')->after('member_id');
            $table->enum('payment_method', ['manual', 'paystack', 'remita', 'wallet'])->change();
        });
    }
};

