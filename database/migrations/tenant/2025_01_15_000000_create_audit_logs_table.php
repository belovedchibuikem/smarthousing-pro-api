<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action'); // create, update, delete, login, logout, approve, reject, etc.
            $table->string('module')->nullable(); // loans, members, contributions, etc.
            $table->string('resource_type')->nullable(); // App\Models\Tenant\Loan, etc.
            $table->uuid('resource_id')->nullable(); // ID of the affected resource
            $table->text('description');
            $table->json('old_values')->nullable(); // Previous values (for updates)
            $table->json('new_values')->nullable(); // New values (for updates)
            $table->json('metadata')->nullable(); // Additional data
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('action');
            $table->index('module');
            $table->index('resource_type');
            $table->index('resource_id');
            $table->index('created_at');
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

