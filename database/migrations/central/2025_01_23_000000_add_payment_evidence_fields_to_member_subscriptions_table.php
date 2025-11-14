<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('member_subscriptions', function (Blueprint $table) {
            // Add payment evidence fields for manual payments
            if (!Schema::connection('mysql')->hasColumn('member_subscriptions', 'payer_name')) {
                $table->string('payer_name')->nullable()->after('payment_reference');
            }
            if (!Schema::connection('mysql')->hasColumn('member_subscriptions', 'payer_phone')) {
                $table->string('payer_phone')->nullable()->after('payer_name');
            }
            if (!Schema::connection('mysql')->hasColumn('member_subscriptions', 'account_details')) {
                $table->text('account_details')->nullable()->after('payer_phone'); // Bank account details or payment instructions
            }
            if (!Schema::connection('mysql')->hasColumn('member_subscriptions', 'payment_evidence')) {
                $table->json('payment_evidence')->nullable()->after('account_details'); // Array of file URLs/paths
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('member_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'payer_name',
                'payer_phone',
                'account_details',
                'payment_evidence',
            ]);
        });
    }
};

