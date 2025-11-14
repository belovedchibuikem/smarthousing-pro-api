<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_transactions', function (Blueprint $table) {
            // Payment approval fields
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'auto_approved'])->default('pending')->after('status');
            $table->uuid('approved_by')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('approval_notes');
            
            // Manual payment specific fields
            $table->string('bank_reference')->nullable()->after('gateway_reference');
            $table->string('bank_name')->nullable()->after('bank_reference');
            $table->string('account_number')->nullable()->after('bank_name');
            $table->string('account_name')->nullable()->after('account_number');
            $table->timestamp('payment_date')->nullable()->after('account_name');
            $table->json('payment_evidence')->nullable()->after('payment_date'); // Store receipt images
            
            // Add indexes
            $table->index('approval_status');
            $table->index('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('platform_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'approval_status',
                'approved_by',
                'approved_at',
                'approval_notes',
                'rejection_reason',
                'bank_reference',
                'bank_name',
                'account_number',
                'account_name',
                'payment_date',
                'payment_evidence'
            ]);
        });
    }
};
