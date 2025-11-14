<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('contribution_plans', 'minimum_amount')) {
            Schema::table('contribution_plans', function (Blueprint $table) {
                $table->decimal('minimum_amount', 15, 2)->default(0)->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contribution_plans', 'minimum_amount')) {
            Schema::table('contribution_plans', function (Blueprint $table) {
                $table->dropColumn('minimum_amount');
            });
        }
    }
};

