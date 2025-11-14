<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('member_number')->unique();
            $table->string('staff_id')->nullable();
            $table->string('ippis_number')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->string('nationality')->default('Nigerian');
            $table->string('state_of_origin')->nullable();
            $table->string('lga')->nullable();
            $table->text('residential_address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('rank')->nullable();
            $table->string('department')->nullable();
            $table->string('command_state')->nullable();
            $table->date('employment_date')->nullable();
            $table->integer('years_of_service')->nullable();
            $table->enum('membership_type', ['regular', 'premium', 'vip'])->default('regular');
            $table->enum('kyc_status', ['pending', 'submitted', 'verified', 'rejected'])->default('pending');
            $table->timestamp('kyc_submitted_at')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
            $table->text('kyc_rejection_reason')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('member_number');
            $table->index('staff_id');
            $table->index('kyc_status');
            $table->index('membership_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
