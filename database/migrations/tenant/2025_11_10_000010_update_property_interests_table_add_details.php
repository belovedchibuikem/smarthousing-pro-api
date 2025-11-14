<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_interests', function (Blueprint $table) {
            $table->json('applicant_snapshot')->nullable()->after('priority');
            $table->json('next_of_kin_snapshot')->nullable()->after('applicant_snapshot');
            $table->decimal('net_salary', 15, 2)->nullable()->after('next_of_kin_snapshot');
            $table->boolean('has_existing_loan')->default(false)->after('net_salary');
            $table->json('existing_loan_types')->nullable()->after('has_existing_loan');
            $table->json('property_snapshot')->nullable()->after('existing_loan_types');
            $table->string('funding_option')->nullable()->after('property_snapshot');
            $table->json('funding_breakdown')->nullable()->after('funding_option');
            $table->json('preferred_payment_methods')->nullable()->after('funding_breakdown');
            $table->json('documents')->nullable()->after('preferred_payment_methods');
            $table->string('signature_path')->nullable()->after('documents');
            $table->timestamp('signed_at')->nullable()->after('signature_path');
            $table->json('mortgage_preferences')->nullable()->after('signed_at');
            $table->boolean('mortgage_flagged')->default(false)->after('mortgage_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('property_interests', function (Blueprint $table) {
            $table->dropColumn([
                'applicant_snapshot',
                'next_of_kin_snapshot',
                'net_salary',
                'has_existing_loan',
                'existing_loan_types',
                'property_snapshot',
                'funding_option',
                'funding_breakdown',
                'preferred_payment_methods',
                'documents',
                'signature_path',
                'signed_at',
                'mortgage_preferences',
                'mortgage_flagged',
            ]);
        });
    }
};

