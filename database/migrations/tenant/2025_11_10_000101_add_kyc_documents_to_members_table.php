<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'kyc_documents')) {
                $table->json('kyc_documents')->nullable()->after('kyc_rejection_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'kyc_documents')) {
                $table->dropColumn('kyc_documents');
            }
        });
    }
};

