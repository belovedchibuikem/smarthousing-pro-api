<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('loan_repayments', function (Blueprint $table) {
            $table->decimal('principal_paid', 15, 2)->default(0)->after('amount');
            $table->decimal('interest_paid', 15, 2)->default(0)->after('principal_paid');
            $table->uuid('property_id')->nullable()->after('loan_id');
            $table->uuid('recorded_by')->nullable()->after('reference');
            
            $table->foreign('property_id')->references('id')->on('properties')->onDelete('set null');
            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('loan_repayments', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->dropIndex(['property_id']);
            $table->dropColumn(['principal_paid', 'interest_paid', 'property_id', 'recorded_by']);
        });
    }
};

