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
        Schema::create('saas_page_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('page_type', 50)->comment('home, community, about, header');
            $table->string('section_type', 50)->comment('hero, stats, features, testimonials, etc.');
            $table->string('section_key', 100);
            $table->string('title')->nullable();
            $table->text('subtitle')->nullable();
            $table->json('content')->default('{}');
            $table->json('media')->default('[]');
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_published')->default(false);
            $table->json('metadata')->default('{}');
            $table->timestamps();
            
            $table->unique(['page_type', 'section_key']);
            $table->index('page_type');
            $table->index('section_type');
            $table->index(['page_type', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saas_page_sections');
    }
};
