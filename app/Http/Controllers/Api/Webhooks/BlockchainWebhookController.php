<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Tenant\BlockchainPropertyRecord;
use App\Services\BlockchainExplorerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BlockchainWebhookController extends Controller
{
    protected BlockchainExplorerService $explorerService;

    public function __construct(BlockchainExplorerService $explorerService)
    {
        $this->explorerService = $explorerService;
    }

    /**
     * Handle blockchain event webhooks
     * 
     * This endpoint receives webhooks from blockchain monitoring services
     * (e.g., Alchemy, Infura, Moralis) when transactions are confirmed
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Verify webhook signature if configured
            $webhookSecret = config('blockchain.webhooks.secret');
            if ($webhookSecret) {
                if (!$this->verifyWebhookSignature($request, $webhookSecret)) {
                    Log::warning('Invalid webhook signature');
                    return response()->json(['error' => 'Invalid signature'], 401);
                }
            }

            $validator = Validator::make($request->all(), [
                'event' => 'required|string',
                'transaction_hash' => 'required|string',
                'network' => 'required|string|in:ethereum,polygon,bsc,arbitrum,optimism',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Invalid webhook payload',
                    'errors' => $validator->errors()
                ], 422);
            }

            $event = $request->input('event');
            $transactionHash = $request->input('transaction_hash');
            $network = $request->input('network');

            // Find blockchain record by transaction hash
            $record = BlockchainPropertyRecord::where('transaction_hash', $transactionHash)
                ->where('network', $network)
                ->first();

            if (!$record) {
                Log::warning("Blockchain record not found for transaction: {$transactionHash}");
                return response()->json(['error' => 'Record not found'], 404);
            }

            // Handle different event types
            switch ($event) {
                case 'transaction.confirmed':
                case 'transaction.mined':
                    $this->handleTransactionConfirmed($record, $request->all());
                    break;

                case 'transaction.failed':
                case 'transaction.reverted':
                    $this->handleTransactionFailed($record, $request->all());
                    break;

                case 'transaction.pending':
                    $this->handleTransactionPending($record, $request->all());
                    break;

                default:
                    Log::info("Unhandled webhook event: {$event}");
                    return response()->json(['message' => 'Event not handled'], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed'
            ]);

        } catch (\Exception $e) {
            Log::error('Webhook processing error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Webhook processing failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle confirmed transaction event
     */
    protected function handleTransactionConfirmed(BlockchainPropertyRecord $record, array $payload): void
    {
        try {
            // Verify transaction on blockchain
            $verification = $this->explorerService->verifyTransactionReceipt(
                $record->transaction_hash,
                $record->network
            );

            if ($verification['confirmed']) {
                $record->update([
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                    'block_number' => $verification['block_number'] ?? $payload['block_number'] ?? null,
                    'verification_notes' => 'Transaction confirmed via webhook',
                ]);

                Log::info("Blockchain record {$record->id} confirmed via webhook");
            }
        } catch (\Exception $e) {
            Log::error("Error handling confirmed transaction: " . $e->getMessage());
        }
    }

    /**
     * Handle failed transaction event
     */
    protected function handleTransactionFailed(BlockchainPropertyRecord $record, array $payload): void
    {
        try {
            $record->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => $payload['reason'] ?? $payload['error'] ?? 'Transaction failed',
            ]);

            Log::info("Blockchain record {$record->id} marked as failed via webhook");
        } catch (\Exception $e) {
            Log::error("Error handling failed transaction: " . $e->getMessage());
        }
    }

    /**
     * Handle pending transaction event
     */
    protected function handleTransactionPending(BlockchainPropertyRecord $record, array $payload): void
    {
        try {
            // Just log - status should already be pending
            Log::info("Blockchain record {$record->id} still pending");
        } catch (\Exception $e) {
            Log::error("Error handling pending transaction: " . $e->getMessage());
        }
    }

    /**
     * Verify webhook signature
     */
    protected function verifyWebhookSignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Webhook-Signature');
        
        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}

