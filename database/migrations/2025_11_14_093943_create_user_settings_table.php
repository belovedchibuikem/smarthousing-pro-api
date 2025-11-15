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
        Schema::create('user_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Notification preferences
            $table->boolean('email_notifications')->default(true);
            $table->boolean('sms_notifications')->default(false);
            $table->boolean('payment_reminders')->default(true);
            $table->boolean('loan_updates')->default(true);
            $table->boolean('investment_updates')->default(true);
            $table->boolean('property_updates')->default(true);
            $table->boolean('contribution_updates')->default(true);
            
            // Account preferences
            $table->string('language', 10)->default('en');
            $table->string('timezone', 50)->default('Africa/Lagos');
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->json('two_factor_recovery_codes')->nullable();
            
            // Privacy preferences
            $table->boolean('profile_visible')->default(true);
            $table->boolean('show_email')->default(false);
            $table->boolean('show_phone')->default(false);
            
            // Other preferences
            $table->json('preferences')->nullable(); // For future extensibility
            
            $table->timestamps();
            
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
