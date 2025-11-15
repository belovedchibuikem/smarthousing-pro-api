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
        Schema::create('saas_community_discussions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('question');
            $table->string('author_name', 255);
            $table->string('author_role', 255)->nullable();
            $table->string('author_avatar_url', 500)->nullable();
            $table->integer('responses_count')->default(0);
            $table->integer('likes_count')->default(0);
            $table->integer('views_count')->default(0);
            $table->json('tags')->default('[]');
            $table->json('top_answer')->nullable();
            $table->json('other_answers')->default('[]');
            $table->integer('order_index')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['is_featured', 'is_active']);
            $table->index('order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saas_community_discussions');
    }
};
