<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mails', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('recipient_id')->constrained('users')->onDelete('cascade');
            $table->string('subject');
            $table->text('body');
            $table->enum('type', ['internal', 'system', 'notification'])->default('internal');
            $table->enum('status', ['draft', 'sent', 'delivered', 'failed'])->default('sent');
            $table->timestamp('sent_at');
            $table->timestamp('read_at')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->timestamps();
            
            $table->index('sender_id');
            $table->index('recipient_id');
            $table->index('type');
            $table->index('status');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mails');
    }
};
