<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('members', 'contribution_plan_id')) {
            Schema::table('members', function (Blueprint $table) {
                $table->foreignUuid('contribution_plan_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('contribution_plans')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('members', 'contribution_plan_id')) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropForeign(['contribution_plan_id']);
                $table->dropColumn('contribution_plan_id');
            });
        }
    }
};

