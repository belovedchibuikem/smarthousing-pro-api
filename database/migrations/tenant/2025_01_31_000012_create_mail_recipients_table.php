<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('mail_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mail_id')->constrained('mails')->onDelete('cascade');
            $table->foreignUuid('recipient_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->enum('type', ['to', 'cc', 'bcc'])->default('to');
            $table->string('email')->nullable(); // For cases where recipient is not a user
            $table->string('name')->nullable();
            $table->enum('status', ['pending', 'delivered', 'failed', 'read'])->default('pending');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index('mail_id');
            $table->index('recipient_id');
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('mail_recipients');
    }
};

