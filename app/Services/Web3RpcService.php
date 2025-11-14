<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Web3 RPC Service
 * Handles direct JSON-RPC calls to blockchain networks
 * Compatible with PHP 8.2+ without requiring external Web3 libraries
 */
class Web3RpcService
{
    /**
     * Call JSON-RPC method
     */
    public function call(string $rpcUrl, string $method, array $params = [], int $timeout = 30): ?array
    {
        try {
            $response = Http::timeout($timeout)->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => time(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['error'])) {
                    Log::error("RPC Error: " . json_encode($data['error']));
                    return null;
                }

                return $data['result'] ?? null;
            }

            return null;
        } catch (Exception $e) {
            Log::error("RPC Call error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get transaction count (nonce) for an address
     */
    public function getTransactionCount(string $rpcUrl, string $address, string $block = 'latest'): ?int
    {
        $result = $this->call($rpcUrl, 'eth_getTransactionCount', [$address, $block]);
        
        if ($result) {
            return hexdec($result);
        }

        return null;
    }

    /**
     * Get gas price
     */
    public function getGasPrice(string $rpcUrl): ?string
    {
        return $this->call($rpcUrl, 'eth_gasPrice');
    }

    /**
     * Estimate gas for a transaction
     */
    public function estimateGas(string $rpcUrl, array $transaction): ?int
    {
        $result = $this->call($rpcUrl, 'eth_estimateGas', [$transaction]);
        
        if ($result) {
            return hexdec($result);
        }

        return null;
    }

    /**
     * Get transaction by hash
     */
    public function getTransaction(string $rpcUrl, string $transactionHash): ?array
    {
        return $this->call($rpcUrl, 'eth_getTransactionByHash', [$transactionHash]);
    }

    /**
     * Get transaction receipt
     */
    public function getTransactionReceipt(string $rpcUrl, string $transactionHash): ?array
    {
        return $this->call($rpcUrl, 'eth_getTransactionReceipt', [$transactionHash]);
    }

    /**
     * Send raw transaction
     */
    public function sendRawTransaction(string $rpcUrl, string $signedTransaction): ?string
    {
        return $this->call($rpcUrl, 'eth_sendRawTransaction', [$signedTransaction]);
    }

    /**
     * Get block number
     */
    public function getBlockNumber(string $rpcUrl): ?int
    {
        $result = $this->call($rpcUrl, 'eth_blockNumber');
        
        if ($result) {
            return hexdec($result);
        }

        return null;
    }

    /**
     * Get balance of an address
     */
    public function getBalance(string $rpcUrl, string $address, string $block = 'latest'): ?string
    {
        return $this->call($rpcUrl, 'eth_getBalance', [$address, $block]);
    }

    /**
     * Convert wei to ether (or native token)
     */
    public function weiToEther(string $wei): float
    {
        // Convert hex to decimal, then divide by 10^18
        $weiDecimal = gmp_init($wei);
        $divisor = gmp_pow(10, 18);
        $ether = gmp_div($weiDecimal, $divisor, GMP_ROUND_ZERO);
        return (float) gmp_strval($ether);
    }

    /**
     * Convert ether to wei
     */
    public function etherToWei(float $ether): string
    {
        $multiplier = gmp_pow(10, 18);
        $wei = gmp_mul((string) $ether, (string) $multiplier);
        return '0x' . gmp_strval($wei, 16);
    }

    /**
     * Convert decimal to hex
     */
    public function decToHex(int $decimal): string
    {
        return '0x' . dechex($decimal);
    }

    /**
     * Convert hex to decimal
     */
    public function hexToDec(string $hex): int
    {
        return hexdec($hex);
    }

    /**
     * Pad hex string to specified length
     */
    public function padHex(string $hex, int $length = 64): string
    {
        // Remove 0x prefix if present
        $hex = str_replace('0x', '', $hex);
        // Pad with zeros
        return '0x' . str_pad($hex, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Convert wei to ether (or native token) from hex
     */
    public function hexWeiToEther(string $weiHex): float
    {
        // Convert hex to decimal, then divide by 10^18
        $weiDecimal = gmp_init(str_replace('0x', '', $weiHex), 16);
        $divisor = gmp_pow(10, 18);
        $ether = gmp_div($weiDecimal, $divisor, GMP_ROUND_ZERO);
        return (float) gmp_strval($ether);
    }
}

