<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            // Ticket/Request fields
            $table->enum('request_type', ['refund', 'stoppage_of_deduction', 'building_plan', 'tdp', 'change_of_ownership', 'other'])->default('refund')->after('member_id');
            $table->enum('status', ['pending', 'approved', 'rejected', 'processing', 'completed'])->default('pending')->after('request_type');
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete()->after('status'); // User who created the request
            $table->text('message')->nullable()->after('reason'); // User's message/request details
            $table->text('admin_response')->nullable()->after('message'); // Admin's response
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('processed_by');
            $table->foreignUuid('rejected_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('admin_response');
            $table->timestamp('requested_at')->nullable()->after('created_at');
            $table->timestamp('approved_at')->nullable()->after('requested_at');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->timestamp('processed_at')->nullable()->after('rejected_at');
            $table->timestamp('completed_at')->nullable()->after('processed_at');
            $table->string('ticket_number')->nullable()->unique()->after('reference'); // Auto-generated ticket number
            
            // Indexes
            $table->index('status');
            $table->index('request_type');
            $table->index('ticket_number');
        });
        
        // Update existing refunds to have default status
        DB::table('refunds')->whereNull('status')->update(['status' => 'completed']);
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'request_type',
                'status',
                'message',
                'admin_response',
                'requested_by',
                'approved_by',
                'rejected_by',
                'rejection_reason',
                'requested_at',
                'approved_at',
                'rejected_at',
                'processed_at',
                'completed_at',
                'ticket_number',
            ]);
        });
    }
};
