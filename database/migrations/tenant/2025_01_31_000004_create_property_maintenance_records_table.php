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
        Schema::connection('tenant')->create('property_maintenance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->uuid('reported_by')->nullable(); // member_id
            $table->string('issue_type')->nullable(); // Plumbing, Electrical, Structural, etc.
            $table->string('priority')->default('medium'); // low, medium, high, critical
            $table->text('description');
            $table->string('status')->default('pending'); // pending, in_progress, completed, cancelled
            $table->uuid('assigned_to')->nullable(); // user_id
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->decimal('actual_cost', 15, 2)->nullable();
            $table->date('reported_date')->nullable();
            $table->date('started_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
            $table->foreign('reported_by')->references('id')->on('members')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['property_id', 'status']);
            $table->index('reported_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('property_maintenance_records');
    }
};

