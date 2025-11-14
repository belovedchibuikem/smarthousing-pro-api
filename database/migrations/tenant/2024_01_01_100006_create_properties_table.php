<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description');
            $table->enum('type', ['apartment', 'house', 'duplex', 'bungalow', 'land', 'commercial']);
            $table->string('location');
            $table->text('address');
            $table->decimal('price', 15, 2);
            $table->decimal('size', 10, 2)->nullable();
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->json('features')->nullable();
            $table->enum('status', ['available', 'allocated', 'sold', 'maintenance'])->default('available');
            $table->boolean('is_featured')->default(false);
            $table->json('coordinates')->nullable();
            $table->timestamps();
            
            $table->index('type');
            $table->index('status');
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
