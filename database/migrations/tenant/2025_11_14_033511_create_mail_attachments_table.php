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
        Schema::create('mail_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mail_id')->constrained('mails')->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path');
            $table->integer('file_size'); // in bytes
            $table->string('mime_type');
            $table->integer('order')->default(0);
            $table->timestamps();
            
            $table->index('mail_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_attachments');
    }
};
