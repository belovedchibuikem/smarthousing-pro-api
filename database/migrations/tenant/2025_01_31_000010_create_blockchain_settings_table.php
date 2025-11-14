<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blockchain_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('primary_network', ['ethereum', 'polygon', 'bsc', 'arbitrum', 'optimism'])->default('ethereum');
            $table->boolean('is_enabled')->default(false);
            $table->boolean('setup_completed')->default(false);
            
            // Network-specific RPC URLs (tenant-specific, can override global defaults)
            $table->text('ethereum_rpc_url')->nullable();
            $table->text('polygon_rpc_url')->nullable();
            $table->text('bsc_rpc_url')->nullable();
            $table->text('arbitrum_rpc_url')->nullable();
            $table->text('optimism_rpc_url')->nullable();
            
            // Explorer API Keys (tenant-specific)
            $table->string('etherscan_api_key')->nullable();
            $table->string('polygonscan_api_key')->nullable();
            $table->string('bscscan_api_key')->nullable();
            $table->string('arbiscan_api_key')->nullable();
            $table->string('optimistic_etherscan_api_key')->nullable();
            
            // Smart Contract Addresses (tenant-specific)
            $table->string('ethereum_contract_address')->nullable();
            $table->string('polygon_contract_address')->nullable();
            $table->string('bsc_contract_address')->nullable();
            $table->string('arbitrum_contract_address')->nullable();
            $table->string('optimism_contract_address')->nullable();
            
            // Webhook settings
            $table->boolean('webhooks_enabled')->default(false);
            $table->string('webhook_secret')->nullable();
            $table->text('webhook_url')->nullable();
            
            // Gas settings
            $table->decimal('gas_price_multiplier', 5, 2)->default(1.20); // 20% buffer
            $table->integer('default_gas_limit')->default(100000);
            
            // Setup metadata
            $table->uuid('setup_completed_by')->nullable();
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamps();
            
            $table->foreign('setup_completed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blockchain_settings');
    }
};

