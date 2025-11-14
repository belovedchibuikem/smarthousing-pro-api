<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blockchain_property_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->string('blockchain_hash')->unique();
            $table->string('transaction_hash')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'failed', 'rejected'])->default('pending');
            $table->text('property_data')->nullable(); // JSON snapshot of property at registration time
            $table->json('ownership_data')->nullable(); // Array of owners with their wallet addresses
            $table->string('network')->default('ethereum'); // blockchain network
            $table->string('contract_address')->nullable(); // smart contract address
            $table->string('token_id')->nullable(); // NFT token ID if using NFT standard
            $table->decimal('gas_fee', 15, 8)->nullable(); // transaction gas fee
            $table->decimal('gas_price', 15, 8)->nullable(); // gas price in wei
            $table->integer('block_number')->nullable(); // block number where transaction was mined
            $table->timestamp('registered_at')->nullable(); // when registration was initiated
            $table->timestamp('confirmed_at')->nullable(); // when blockchain confirmed
            $table->timestamp('failed_at')->nullable(); // when transaction failed
            $table->text('failure_reason')->nullable();
            $table->text('verification_notes')->nullable();
            $table->uuid('registered_by'); // admin user who initiated registration
            $table->uuid('verified_by')->nullable(); // admin user who verified
            $table->timestamps();
            
            $table->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
            $table->foreign('registered_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index('property_id');
            $table->index('blockchain_hash');
            $table->index('transaction_hash');
            $table->index('status');
            $table->index('network');
            $table->index('block_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blockchain_property_records');
    }
};

