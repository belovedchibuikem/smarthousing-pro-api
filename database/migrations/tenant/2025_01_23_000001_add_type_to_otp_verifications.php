<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->enum('type', ['registration', 'password_reset', 'email_verification'])->default('registration')->after('email');
            $table->string('phone')->nullable()->after('email');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'phone']);
        });
    }
};

