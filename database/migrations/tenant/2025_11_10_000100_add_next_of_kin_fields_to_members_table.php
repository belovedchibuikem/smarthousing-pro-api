<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('next_of_kin_name')->nullable()->after('kyc_rejection_reason');
            $table->string('next_of_kin_relationship')->nullable()->after('next_of_kin_name');
            $table->string('next_of_kin_phone')->nullable()->after('next_of_kin_relationship');
            $table->string('next_of_kin_email')->nullable()->after('next_of_kin_phone');
            $table->text('next_of_kin_address')->nullable()->after('next_of_kin_email');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'next_of_kin_name',
                'next_of_kin_relationship',
                'next_of_kin_phone',
                'next_of_kin_email',
                'next_of_kin_address',
            ]);
        });
    }
};

