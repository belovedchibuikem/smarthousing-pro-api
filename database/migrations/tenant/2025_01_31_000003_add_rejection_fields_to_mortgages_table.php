<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('mortgages', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('approved_by');
            $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
            $table->uuid('rejected_by')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('mortgages', function (Blueprint $table) {
            $table->dropColumn(['rejection_reason', 'rejected_at', 'rejected_by']);
        });
    }
};

