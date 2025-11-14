<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('mail_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mail_id')->constrained('mails')->onDelete('cascade');
            $table->string('name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size'); // in bytes
            $table->integer('order')->default(0);
            $table->timestamps();
            
            $table->index('mail_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('mail_attachments');
    }
};

