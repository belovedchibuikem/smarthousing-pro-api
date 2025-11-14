<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'product_id')) {
                $table->uuid('product_id')->nullable()->after('member_id');
                $table->foreign('product_id')
                    ->references('id')
                    ->on('loan_products')
                    ->nullOnDelete();
                $table->index('product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropIndex('loans_product_id_index');
                $table->dropColumn('product_id');
            }
        });
    }
};

