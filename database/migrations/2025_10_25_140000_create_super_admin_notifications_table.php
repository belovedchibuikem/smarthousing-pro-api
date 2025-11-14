<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_admin_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('super_admin_id');
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('super_admin_id')
                ->references('id')
                ->on('super_admins')
                ->onDelete('cascade');

            $table->index(['super_admin_id', 'read_at']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admin_notifications');
    }
};
