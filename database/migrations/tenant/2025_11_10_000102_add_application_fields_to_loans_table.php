<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'product_id')) {
                $table->uuid('product_id')->nullable()->after('member_id');
            }

            if (! Schema::hasColumn('loans', 'monthly_payment')) {
                $table->decimal('monthly_payment', 15, 2)->nullable()->after('application_date');
            }

            if (! Schema::hasColumn('loans', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->nullable()->after('monthly_payment');
            }

            if (! Schema::hasColumn('loans', 'interest_amount')) {
                $table->decimal('interest_amount', 15, 2)->nullable()->after('total_amount');
            }

            if (! Schema::hasColumn('loans', 'processing_fee')) {
                $table->decimal('processing_fee', 15, 2)->nullable()->after('interest_amount');
            }

            if (! Schema::hasColumn('loans', 'required_documents')) {
                $table->json('required_documents')->nullable()->after('processing_fee');
            }

            if (! Schema::hasColumn('loans', 'application_metadata')) {
                $table->json('application_metadata')->nullable()->after('required_documents');
            }

            if (! Schema::hasColumn('loans', 'disbursed_at')) {
                $table->timestamp('disbursed_at')->nullable()->after('rejected_by');
            }

            if (! Schema::hasColumn('loans', 'disbursed_by')) {
                $table->uuid('disbursed_by')->nullable()->after('disbursed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'application_metadata')) {
                $table->dropColumn('application_metadata');
            }
            if (Schema::hasColumn('loans', 'required_documents')) {
                $table->dropColumn('required_documents');
            }
            if (Schema::hasColumn('loans', 'processing_fee')) {
                $table->dropColumn('processing_fee');
            }
            if (Schema::hasColumn('loans', 'interest_amount')) {
                $table->dropColumn('interest_amount');
            }
            if (Schema::hasColumn('loans', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('loans', 'monthly_payment')) {
                $table->dropColumn('monthly_payment');
            }
            if (Schema::hasColumn('loans', 'product_id')) {
                $table->dropColumn('product_id');
            }
            if (Schema::hasColumn('loans', 'disbursed_by')) {
                $table->dropColumn('disbursed_by');
            }
            if (Schema::hasColumn('loans', 'disbursed_at')) {
                $table->dropColumn('disbursed_at');
            }
        });
    }
};

