<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statutory_charges', function (Blueprint $table) {
            $table->uuid('department_id')->nullable()->after('member_id');
            $table->foreign('department_id')
                ->references('id')
                ->on('statutory_charge_departments')
                ->onDelete('set null');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('statutory_charges', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};

