<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Status lifecycle fields used by controllers/resources
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('membership_type');
            $table->timestamp('activated_at')->nullable()->after('status');
            $table->uuid('activated_by')->nullable()->after('activated_at');
            $table->timestamp('deactivated_at')->nullable()->after('activated_by');
            $table->uuid('deactivated_by')->nullable()->after('deactivated_at');
            $table->timestamp('suspended_at')->nullable()->after('deactivated_by');
            $table->uuid('suspended_by')->nullable()->after('suspended_at');
            $table->text('suspension_reason')->nullable()->after('suspended_by');

            // Helpful indexes
            $table->index('status');
            $table->index('activated_by');
            $table->index('deactivated_by');
            $table->index('suspended_by');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['activated_by']);
            $table->dropIndex(['deactivated_by']);
            $table->dropIndex(['suspended_by']);

            $table->dropColumn([
                'status',
                'activated_at',
                'activated_by',
                'deactivated_at',
                'deactivated_by',
                'suspended_at',
                'suspended_by',
                'suspension_reason',
            ]);
        });
    }
};


