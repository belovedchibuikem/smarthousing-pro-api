<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add payer information fields for manual payments
            if (!Schema::hasColumn('payments', 'payer_name')) {
                $table->string('payer_name')->nullable()->after('account_name');
            }
            if (!Schema::hasColumn('payments', 'payer_phone')) {
                $table->string('payer_phone')->nullable()->after('payer_name');
            }
            if (!Schema::hasColumn('payments', 'account_details')) {
                $table->text('account_details')->nullable()->after('payer_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'payer_name',
                'payer_phone',
                'account_details',
            ]);
        });
    }
};

