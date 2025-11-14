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
        Schema::create('property_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('property_id')->constrained('properties')->onDelete('cascade');
            $table->foreignUuid('member_id')->constrained('members')->onDelete('cascade');
            $table->enum('transfer_type', ['sale', 'gift', 'external']);
            $table->string('buyer_name');
            $table->string('buyer_contact')->nullable();
            $table->string('buyer_email')->nullable();
            $table->decimal('sale_price', 15, 2);
            $table->decimal('transfer_fee', 15, 2);
            $table->text('reason')->nullable();
            $table->json('documents')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index('property_id');
            $table->index('member_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_transfers');
    }
};
