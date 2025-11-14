<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('tenant')->table('statutory_charge_types', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('statutory_charge_types', 'default_amount')) {
                $table->decimal('default_amount', 15, 2)->nullable()->after('description');
            }
            if (!Schema::connection('tenant')->hasColumn('statutory_charge_types', 'frequency')) {
                $table->enum('frequency', ['monthly', 'quarterly', 'bi_annually', 'annually'])->default('annually')->after('default_amount');
            }
            if (!Schema::connection('tenant')->hasIndex('statutory_charge_types', 'statutory_charge_types_frequency_index')) {
                $table->index('frequency');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->table('statutory_charge_types', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('statutory_charge_types', 'frequency')) {
                $table->dropIndex('statutory_charge_types_frequency_index');
                $table->dropColumn('frequency');
            }
            if (Schema::connection('tenant')->hasColumn('statutory_charge_types', 'default_amount')) {
                $table->dropColumn('default_amount');
            }
        });
    }
};
