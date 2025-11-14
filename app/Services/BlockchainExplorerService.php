<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant\BlockchainSetting;
use Exception;

class BlockchainExplorerService
{
    /**
     * Get transaction details from blockchain explorer
     */
    public function getTransaction(string $transactionHash, string $network = 'ethereum'): ?array
    {
        try {
            // Get tenant-specific settings
            $settings = \App\Models\Tenant\BlockchainSetting::getInstance();
            
            $config = config("blockchain.networks.{$network}");
            $apiUrl = $config['explorer_api_url'] ?? null;
            
            // Use tenant-specific API key if available
            $apiKey = $settings->getExplorerApiKey($network);
            
            if (!$apiUrl || !$apiKey) {
                Log::warning("Blockchain explorer not configured for network: {$network}");
                return null;
            }

            // Different explorers have slightly different API endpoints
            $endpoint = $this->getTransactionEndpoint($network);
            
            $response = Http::timeout(10)->get($apiUrl, [
                'module' => 'proxy',
                'action' => $endpoint,
                'txhash' => $transactionHash,
                'apikey' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['status']) && $data['status'] === '1' && isset($data['result'])) {
                    return $this->parseTransactionResult($data['result'], $network);
                } elseif (isset($data['result']) && !empty($data['result'])) {
                    // Some APIs return directly in result
                    return $this->parseTransactionResult($data['result'], $network);
                }
            }

            return null;
        } catch (Exception $e) {
            Log::error("Blockchain explorer error for {$network}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify transaction receipt (confirmation status)
     */
    public function verifyTransactionReceipt(string $transactionHash, string $network = 'ethereum'): array
    {
        try {
            // Get tenant-specific settings
            $settings = BlockchainSetting::getInstance();
            
            $config = config("blockchain.networks.{$network}");
            $apiUrl = $config['explorer_api_url'] ?? null;
            
            // Use tenant-specific API key if available
            $apiKey = $settings->getExplorerApiKey($network);
            
            if (!$apiUrl || !$apiKey) {
                return [
                    'valid' => false,
                    'error' => 'Blockchain explorer not configured',
                ];
            }

            $response = Http::timeout(10)->get($apiUrl, [
                'module' => 'proxy',
                'action' => 'eth_getTransactionReceipt',
                'txhash' => $transactionHash,
                'apikey' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['result']) && $data['result'] !== null) {
                    $receipt = $data['result'];
                    
                    // Check if transaction was successful (status = 0x1)
                    $status = hexdec($receipt['status'] ?? '0x0');
                    $isConfirmed = $status === 1;
                    
                    return [
                        'valid' => $isConfirmed,
                        'confirmed' => $isConfirmed,
                        'block_number' => isset($receipt['blockNumber']) ? hexdec($receipt['blockNumber']) : null,
                        'gas_used' => isset($receipt['gasUsed']) ? hexdec($receipt['gasUsed']) : null,
                        'cumulative_gas_used' => isset($receipt['cumulativeGasUsed']) ? hexdec($receipt['cumulativeGasUsed']) : null,
                        'contract_address' => $receipt['contractAddress'] ?? null,
                        'logs' => $receipt['logs'] ?? [],
                    ];
                }
            }

            return [
                'valid' => false,
                'confirmed' => false,
                'error' => 'Transaction not found or not confirmed',
            ];
        } catch (Exception $e) {
            Log::error("Transaction verification error for {$network}: " . $e->getMessage());
            return [
                'valid' => false,
                'confirmed' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get transaction count (nonce) for an address
     */
    public function getTransactionCount(string $address, string $network = 'ethereum'): ?int
    {
        try {
            // Get tenant-specific settings
            $settings = BlockchainSetting::getInstance();
            
            $config = config("blockchain.networks.{$network}");
            $apiUrl = $config['explorer_api_url'] ?? null;
            
            // Use tenant-specific API key if available
            $apiKey = $settings->getExplorerApiKey($network);
            
            if (!$apiUrl || !$apiKey) {
                return null;
            }

            $response = Http::timeout(10)->get($apiUrl, [
                'module' => 'proxy',
                'action' => 'eth_getTransactionCount',
                'address' => $address,
                'tag' => 'latest',
                'apikey' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['result'])) {
                    return hexdec($data['result']);
                }
            }

            return null;
        } catch (Exception $e) {
            Log::error("Get transaction count error for {$network}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get gas price from blockchain
     */
    public function getGasPrice(string $network = 'ethereum'): ?string
    {
        try {
            // Get tenant-specific settings
            $settings = BlockchainSetting::getInstance();
            
            $config = config("blockchain.networks.{$network}");
            $apiUrl = $config['explorer_api_url'] ?? null;
            
            // Use tenant-specific API key if available
            $apiKey = $settings->getExplorerApiKey($network);
            
            if (!$apiUrl || !$apiKey) {
                return null;
            }

            $response = Http::timeout(10)->get($apiUrl, [
                'module' => 'proxy',
                'action' => 'eth_gasPrice',
                'apikey' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['result'])) {
                    return $data['result']; // Returns in hex format
                }
            }

            return null;
        } catch (Exception $e) {
            Log::error("Get gas price error for {$network}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get transaction endpoint name based on network
     */
    private function getTransactionEndpoint(string $network): string
    {
        // Most Ethereum-compatible explorers use the same endpoints
        return 'eth_getTransactionByHash';
    }

    /**
     * Parse transaction result from explorer API
     */
    private function parseTransactionResult(array $result, string $network): array
    {
        return [
            'hash' => $result['hash'] ?? null,
            'from' => $result['from'] ?? null,
            'to' => $result['to'] ?? null,
            'value' => isset($result['value']) ? hexdec($result['value']) : 0,
            'gas' => isset($result['gas']) ? hexdec($result['gas']) : null,
            'gas_price' => isset($result['gasPrice']) ? hexdec($result['gasPrice']) : null,
            'nonce' => isset($result['nonce']) ? hexdec($result['nonce']) : null,
            'block_number' => isset($result['blockNumber']) ? hexdec($result['blockNumber']) : null,
            'block_hash' => $result['blockHash'] ?? null,
            'transaction_index' => isset($result['transactionIndex']) ? hexdec($result['transactionIndex']) : null,
            'input' => $result['input'] ?? null,
        ];
    }
}

