<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blockchain_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // Wallet name/identifier
            $table->enum('network', ['ethereum', 'polygon', 'bsc', 'arbitrum', 'optimism'])->default('ethereum');
            $table->string('address'); // Wallet address (public key)
            $table->text('private_key_encrypted')->nullable(); // Encrypted private key (use encryption)
            $table->text('mnemonic_encrypted')->nullable(); // Encrypted mnemonic phrase
            $table->decimal('balance', 30, 18)->default(0); // Native token balance (ETH, MATIC, etc.)
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Default wallet for network
            $table->text('notes')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            $table->unique(['network', 'address']);
            $table->index('network');
            $table->index('is_active');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blockchain_wallets');
    }
};

