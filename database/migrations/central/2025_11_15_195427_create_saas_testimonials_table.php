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
        Schema::create('saas_testimonials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('role', 255);
            $table->text('content');
            $table->integer('rating')->default(5);
            $table->string('avatar_url', 500)->nullable();
            $table->string('company', 255)->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->check('rating >= 1 AND rating <= 5');
            $table->index(['is_featured', 'is_active']);
            $table->index('order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saas_testimonials');
    }
};
