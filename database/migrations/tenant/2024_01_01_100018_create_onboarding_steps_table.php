<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('members')->onDelete('cascade');
            $table->integer('step_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type'); // form, document_upload, verification, etc.
            $table->enum('status', ['pending', 'completed', 'skipped'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->text('skip_reason')->nullable();
            $table->json('data')->nullable(); // step-specific data
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            
            $table->index('member_id');
            $table->index('step_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_steps');
    }
};
