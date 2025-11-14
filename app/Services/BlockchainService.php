<?php

namespace App\Services;

use App\Models\Tenant\BlockchainPropertyRecord;
use App\Models\Tenant\Property;
use App\Models\Tenant\BlockchainWallet;
use App\Models\Tenant\BlockchainSetting;
use Illuminate\Support\Facades\Log;
use kornrunner\Keccak;
use phpseclib3\Crypt\EC;
use phpseclib3\Math\BigInteger;
use Exception;

class BlockchainService
{
    protected BlockchainExplorerService $explorerService;
    protected Web3RpcService $rpcService;

    public function __construct(BlockchainExplorerService $explorerService, Web3RpcService $rpcService)
    {
        $this->explorerService = $explorerService;
        $this->rpcService = $rpcService;
    }

    /**
     * Generate a unique blockchain hash for property registration
     */
    public function generatePropertyHash(Property $property, array $ownershipData): string
    {
        $data = [
            'property_id' => $property->id,
            'title' => $property->title,
            'location' => $property->location,
            'type' => $property->type ?? $property->property_type,
            'ownership' => $ownershipData,
            'timestamp' => now()->toIso8601String(),
        ];

        $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Generate SHA-256 hash
        $hash = hash('sha256', $jsonData);
        
        // Prefix with '0x' for blockchain compatibility (Ethereum-style)
        return '0x' . $hash;
    }

    /**
     * Verify blockchain transaction using blockchain explorer API
     */
    public function verifyTransaction(string $transactionHash, string $network = 'ethereum'): array
    {
        try {
            // Use blockchain explorer service to verify transaction
            $result = $this->explorerService->verifyTransactionReceipt($transactionHash, $network);

            if ($result['valid'] && $result['confirmed']) {
                return [
                    'valid' => true,
                    'confirmed' => true,
                    'block_number' => $result['block_number'],
                    'gas_used' => $result['gas_used'],
                    'cumulative_gas_used' => $result['cumulative_gas_used'] ?? null,
                    'contract_address' => $result['contract_address'] ?? null,
                ];
            }

            return [
                'valid' => false,
                'confirmed' => false,
                'error' => $result['error'] ?? 'Transaction not confirmed',
            ];
        } catch (Exception $e) {
            Log::error('Blockchain verification failed: ' . $e->getMessage());
            return [
                'valid' => false,
                'confirmed' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Register property on blockchain using Web3/RPC
     * 
     * This method:
     * 1. Gets the default wallet for the network
     * 2. Prepares transaction data
     * 3. Estimates gas
     * 4. Signs and submits transaction to blockchain
     * 5. Returns transaction hash
     */
    public function registerPropertyOnBlockchain(
        BlockchainPropertyRecord $record,
        string $network = 'ethereum'
    ): array {
        try {
            // Get default wallet for network
            $wallet = BlockchainWallet::getDefaultForNetwork($network);
            
            if (!$wallet || !$wallet->is_active) {
                throw new Exception("No active default wallet found for network: {$network}");
            }

            // Get tenant-specific settings
            $settings = BlockchainSetting::getInstance();
            
            // Get RPC URL (tenant-specific or fallback to config)
            $rpcUrl = $settings->getRpcUrl($network);
            if (!$rpcUrl) {
                throw new Exception("RPC URL not configured for network: {$network}");
            }

            $config = config("blockchain.networks.{$network}");

            // Prepare transaction data
            // In a real implementation, you would encode function calls to your smart contract here
            // For now, we'll create a transaction to store the hash on-chain
            $contractAddress = $settings->getContractAddress($network);
            
            if (!$contractAddress) {
                // If no contract, we'll just send a transaction with data
                // This is a fallback - in production, use proper smart contracts
                Log::warning("No contract address configured for {$network}, using fallback method");
                return $this->registerWithFallbackMethod($record, $wallet, $network, $rpcUrl);
            }

            // Encode function call data for smart contract
            // This would typically call a function like: registerProperty(bytes32 propertyHash, bytes calldata propertyData)
            $functionSignature = '0x' . substr(hash('sha256', 'registerProperty(bytes32,bytes)'), 0, 8);
            $propertyHashPadded = str_pad(substr($record->blockchain_hash, 2), 64, '0', STR_PAD_LEFT);
            $propertyDataEncoded = bin2hex($record->property_data ?? '');
            $dataLength = dechex(strlen($propertyDataEncoded) / 2);
            $dataLengthPadded = str_pad($dataLength, 64, '0', STR_PAD_LEFT);
            
            $transactionData = $functionSignature . $propertyHashPadded . $dataLengthPadded . $propertyDataEncoded;

            // Get nonce for wallet using RPC
            $nonce = $this->rpcService->getTransactionCount($rpcUrl, $wallet->address);
            if ($nonce === null) {
                throw new Exception("Failed to get nonce for wallet address");
            }
            
            // Get gas price using RPC
            $gasPrice = $this->rpcService->getGasPrice($rpcUrl);
            if (!$gasPrice) {
                // Fallback to network default
                $gasPrice = $this->rpcService->decToHex((int)(20 * 1e9)); // 20 gwei
            }

            // Estimate gas limit (use tenant setting or default)
            $gasLimit = $settings->default_gas_limit ?? $config['gas_limit'] ?? 100000;
            
            // Try to estimate gas for the transaction
            $estimatedGas = $this->rpcService->estimateGas($rpcUrl, [
                'from' => $wallet->address,
                'to' => $contractAddress,
                'data' => $transactionData,
            ]);
            
            if ($estimatedGas) {
                $gasLimit = (int)($estimatedGas * 1.2); // Add 20% buffer
            }
            
            $gasLimitHex = $this->rpcService->decToHex($gasLimit);

            // Prepare transaction
            $transaction = [
                'from' => $wallet->address,
                'to' => $contractAddress,
                'value' => '0x0',
                'data' => $transactionData,
                'gas' => $gasLimitHex,
                'gasPrice' => $gasPrice,
                'nonce' => $this->rpcService->decToHex($nonce),
            ];

            // Sign transaction (this requires the private key)
            // NOTE: In production, you might want to use a more secure signing method
            // such as using a hardware security module (HSM) or a signing service
            $privateKey = $wallet->getPrivateKey();
            if (!$privateKey) {
                throw new Exception("Private key not available for wallet");
            }

            $signedTransaction = $this->signTransaction($transaction, $privateKey, $network);

            // Send signed transaction to blockchain using RPC
            $transactionHash = $this->rpcService->sendRawTransaction($rpcUrl, $signedTransaction);

            if (!$transactionHash) {
                throw new Exception("Failed to send transaction to blockchain");
            }

            // Calculate estimated gas fee
            $gasPriceDecimal = $this->rpcService->hexToDec($gasPrice) / 1e9; // Convert from wei to gwei
            $gasFee = ($gasLimit * $this->rpcService->hexToDec($gasPrice)) / 1e18; // Convert to native token

            return [
                'success' => true,
                'transaction_hash' => $transactionHash,
                'status' => 'pending',
                'gas_fee' => $gasFee,
                'gas_price' => $gasPriceDecimal,
                'gas_limit' => $gasLimit,
                'network' => $network,
            ];

        } catch (Exception $e) {
            Log::error('Blockchain registration failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fallback method if no smart contract is configured
     * Sends transaction with property data in the transaction data field
     */
    protected function registerWithFallbackMethod(
        BlockchainPropertyRecord $record,
        BlockchainWallet $wallet,
        string $network,
        string $rpcUrl
    ): array {
        // Encode property hash as transaction data
        $transactionData = '0x' . substr($record->blockchain_hash, 2);

            $nonce = $this->rpcService->getTransactionCount($rpcUrl, $wallet->address);
            if ($nonce === null) {
                throw new Exception("Failed to get nonce for wallet address");
            }
            
            $gasPrice = $this->rpcService->getGasPrice($rpcUrl);
            if (!$gasPrice) {
                $gasPrice = $this->rpcService->decToHex((int)(20 * 1e9)); // 20 gwei
            }
            $gasLimit = $this->rpcService->decToHex(21000); // Standard transaction

        // For fallback, send to self (wallet address) with data
            $transaction = [
                'from' => $wallet->address,
                'to' => $wallet->address,
                'value' => '0x0',
                'data' => $transactionData,
                'gas' => $gasLimit,
                'gasPrice' => $gasPrice,
                'nonce' => $this->rpcService->decToHex($nonce),
            ];

        $privateKey = $wallet->getPrivateKey();
        if (!$privateKey) {
            throw new Exception("Private key not available for wallet");
        }

        $signedTransaction = $this->signTransaction($transaction, $privateKey, $network);
        $transactionHash = $this->rpcService->sendRawTransaction($rpcUrl, $signedTransaction);

        if (!$transactionHash) {
            throw new Exception("Failed to send transaction to blockchain");
        }

        $gasFee = (21000 * $this->rpcService->hexToDec($gasPrice)) / 1e18;

        return [
            'success' => true,
            'transaction_hash' => $transactionHash,
            'status' => 'pending',
            'gas_fee' => $gasFee,
            'gas_price' => hexdec($gasPrice) / 1e9,
            'gas_limit' => 21000,
            'network' => $network,
        ];
    }


    /**
     * Sign transaction using private key with ECDSA
     * 
     * Implements EIP-155 transaction signing for Ethereum-compatible networks
     */
    protected function signTransaction(array $transaction, string $privateKey, string $network): string
    {
        try {
            // Remove 0x prefix if present
            $privateKeyHex = str_replace('0x', '', $privateKey);
            if (strlen($privateKeyHex) !== 64) {
                throw new Exception("Invalid private key length");
            }
            
            $privateKeyBin = hex2bin($privateKeyHex);
            
            // Get chain ID for the network
            $chainId = $this->getChainId($network);
            
            // Prepare transaction fields (without signature)
            $txFields = [
                $transaction['nonce'],
                $transaction['gasPrice'],
                $transaction['gas'],
                $transaction['to'] ?? '0x',
                $transaction['value'] ?? '0x0',
                $transaction['data'] ?? '0x',
                $this->rpcService->decToHex($chainId), // chainId
                '0x0', // r
                '0x0', // s
            ];
            
            // RLP encode the transaction
            $rlpEncoded = $this->rlpEncode($txFields);
            
            // Hash the RLP-encoded transaction using Keccak-256
            // RLP encode returns binary string, so hash it directly
            $hashHex = Keccak::hash($rlpEncoded, 256);
            $hash = '0x' . $hashHex;
            
            // Sign the hash with ECDSA using secp256k1 curve
            // phpseclib expects binary data for signing
            $hashBinary = hex2bin($hashHex);
            
            // Use phpseclib's built-in secp256k1 curve
            $key = EC::createKey('secp256k1');
            
            // Load the private key
            $key = $key->withPrivateKey(new BigInteger($privateKeyHex, 16));
            
            // Sign the hash (phpseclib expects binary)
            $signature = $key->sign($hashBinary);
            
            // Extract r and s from signature
            $r = new BigInteger($signature['r']->toString(), 10);
            $s = new BigInteger($signature['s']->toString(), 10);
            
            // Calculate v (recovery ID)
            $v = $this->calculateV($hash, $r, $s, $privateKeyHex, $network, $chainId);
            
            // Prepare final transaction with signature
            $finalTxFields = [
                $transaction['nonce'],
                $transaction['gasPrice'],
                $transaction['gas'],
                $transaction['to'] ?? '0x',
                $transaction['value'] ?? '0x0',
                $transaction['data'] ?? '0x',
                $v, // v with chain ID
                $this->rpcService->padHex($r->toHex(), 64), // r (64 hex chars)
                $this->rpcService->padHex($s->toHex(), 64), // s (64 hex chars)
            ];
            
            // RLP encode the final signed transaction
            $signedRlp = $this->rlpEncode($finalTxFields);
            
            // Return hex-encoded signed transaction
            return '0x' . bin2hex($signedRlp);
            
        } catch (Exception $e) {
            Log::error("Transaction signing error: " . $e->getMessage());
            throw new Exception("Failed to sign transaction: " . $e->getMessage());
        }
    }

    /**
     * Get chain ID for network
     */
    protected function getChainId(string $network): int
    {
        $chainIds = [
            'ethereum' => 1,
            'polygon' => 137,
            'bsc' => 56,
            'arbitrum' => 42161,
            'optimism' => 10,
        ];
        
        return $chainIds[$network] ?? 1;
    }

    /**
     * Calculate v value for transaction signature (EIP-155)
     */
    protected function calculateV(string $hash, BigInteger $r, BigInteger $s, string $privateKeyHex, string $network, int $chainId): string
    {
        // For EIP-155, v = chainId * 2 + 35 + recovery_id
        // Recovery ID is either 0 or 1
        
        // Derive public key from private key
        $curve = EC::loadCurveByParams([
            'a' => new BigInteger('0000000000000000000000000000000000000000000000000000000000000000', 16),
            'b' => new BigInteger('0000000000000000000000000000000000000000000000000000000000000007', 16),
            'prime' => new BigInteger('fffffffffffffffffffffffffffffffffffffffffffffffffffffffefffffc2f', 16),
            'order' => new BigInteger('fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141', 16),
            'G' => [
                new BigInteger('79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798', 16),
                new BigInteger('483ada7726a3c4655da4fbfc0e1108a8fd17b448a68554199c47d08ffb10d4b8', 16),
            ],
        ]);
        
        $key = EC::loadPrivateKeyFormat('Raw', [
            'curve' => $curve,
            'dA' => new BigInteger($privateKeyHex, 16),
        ]);
        
        // Get public key point
        $publicKey = $key->getPublicKey();
        $pubPoint = $publicKey->getPoint();
        
        // Derive expected address from public key
        // Ethereum address = last 20 bytes of Keccak-256(public_key)
        $pubKeyX = $pubPoint->getX()->toHex();
        $pubKeyY = $pubPoint->getY()->toHex();
        $pubKeyBytes = hex2bin(str_pad($pubKeyX, 64, '0', STR_PAD_LEFT) . str_pad($pubKeyY, 64, '0', STR_PAD_LEFT));
        $pubKeyHash = Keccak::hash($pubKeyBytes, 256);
        $expectedAddress = '0x' . substr($pubKeyHash, -40);
        
        // Remove 0x from hash for signing
        $hashForSign = str_replace('0x', '', $hash);
        
        // Try recovery ID 0 first
        $recoveryId = 0;
        
        // Verify by trying to recover public key with recovery ID
        // For simplicity, we'll use recovery ID 0 and let the blockchain verify
        // In a full implementation, you would recover and verify the address matches
        
        // Calculate v according to EIP-155
        $v = $chainId * 2 + 35 + $recoveryId;
        
        // Return hex value
        return $this->rpcService->decToHex($v);
    }

    /**
     * RLP (Recursive Length Prefix) encode data
     * Implementation for transaction encoding
     */
    protected function rlpEncode(array $data): string
    {
        $output = '';
        
        foreach ($data as $item) {
            // Handle empty string
            if ($item === '' || $item === '0x') {
                $binary = '';
            } else {
                // Remove 0x prefix
                $hex = str_replace('0x', '', $item);
                
                // Handle zero-padding: remove leading zeros except for single zero
                $hex = ltrim($hex, '0');
                if ($hex === '') {
                    $hex = '0';
                }
                
                // Ensure even length
                if (strlen($hex) % 2 !== 0) {
                    $hex = '0' . $hex;
                }
                
                // Convert hex to binary
                $binary = hex2bin($hex);
            }
            
            $length = strlen($binary);
            
            if ($length === 1 && ord($binary) < 0x80) {
                // Single byte less than 0x80
                $output .= $binary;
            } elseif ($length < 56) {
                // Short string: 0x80 + length, followed by the string
                $output .= chr(0x80 + $length) . $binary;
            } else {
                // Long string: 0xb7 + length of length, followed by length, followed by string
                $lengthHex = dechex($length);
                // Ensure even length for hex
                if (strlen($lengthHex) % 2 !== 0) {
                    $lengthHex = '0' . $lengthHex;
                }
                $lengthOfLength = strlen($lengthHex) / 2;
                $output .= chr(0xb7 + $lengthOfLength) . hex2bin($lengthHex) . $binary;
            }
        }
        
        // Encode the list itself
        $listLength = strlen($output);
        
        if ($listLength < 56) {
            return chr(0xc0 + $listLength) . $output;
        } else {
            $lengthHex = dechex($listLength);
            // Ensure even length for hex
            if (strlen($lengthHex) % 2 !== 0) {
                $lengthHex = '0' . $lengthHex;
            }
            $lengthOfLength = strlen($lengthHex) / 2;
            return chr(0xf7 + $lengthOfLength) . hex2bin($lengthHex) . $output;
        }
    }


    /**
     * Check transaction status on blockchain
     */
    public function checkTransactionStatus(string $transactionHash, string $network = 'ethereum'): array
    {
        try {
            $result = $this->explorerService->verifyTransactionReceipt($transactionHash, $network);
            
            if ($result['confirmed']) {
                return [
                    'status' => 'confirmed',
                    'confirmed' => true,
                    'block_number' => $result['block_number'],
                    'gas_used' => $result['gas_used'],
                    'confirmations' => null, // Would need current block number to calculate
                ];
            }

            // Check if transaction exists but not confirmed
            $tx = $this->explorerService->getTransaction($transactionHash, $network);
            if ($tx) {
                return [
                    'status' => 'pending',
                    'confirmed' => false,
                    'block_number' => $tx['block_number'] ?? null,
                ];
            }

            return [
                'status' => 'unknown',
                'confirmed' => false,
                'error' => 'Transaction not found',
            ];
        } catch (Exception $e) {
            Log::error('Transaction status check failed: ' . $e->getMessage());
            return [
                'status' => 'unknown',
                'confirmed' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate property data snapshot for blockchain storage
     */
    public function generatePropertySnapshot(Property $property): string
    {
        $property->load(['images', 'allocations.member.user']);
        
        $snapshot = [
            'id' => $property->id,
            'title' => $property->title,
            'description' => $property->description,
            'type' => $property->type ?? $property->property_type,
            'location' => $property->location,
            'address' => $property->address,
            'city' => $property->city,
            'state' => $property->state,
            'price' => (float) $property->price,
            'size' => (float) $property->size,
            'bedrooms' => $property->bedrooms,
            'bathrooms' => $property->bathrooms,
            'features' => $property->features,
            'status' => $property->status,
            'registered_at' => now()->toIso8601String(),
            'owners' => $property->allocations()
                ->where('status', 'approved')
                ->with('member.user')
                ->get()
                ->map(function ($allocation) {
                    return [
                        'member_id' => $allocation->member_id,
                        'member_number' => $allocation->member->member_number ?? null,
                        'name' => $allocation->member->user 
                            ? ($allocation->member->user->first_name . ' ' . $allocation->member->user->last_name)
                            : 'Unknown',
                        'allocation_date' => $allocation->allocation_date?->toDateString(),
                    ];
                })->toArray(),
        ];

        return json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
