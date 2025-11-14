<?php

namespace App\Services\Payment;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class RemitaService
{
    protected Client $client;
    protected string $merchantId;
    protected string $apiKey;
    protected string $serviceTypeId;

    public function __construct(
        ?string $merchantId = null,
        ?string $apiKey = null,
        ?string $serviceTypeId = null,
        ?Client $client = null
    ) {
        $this->client = $client ?? new Client();
        $this->merchantId = $merchantId ?? (string) config('services.remita.merchant_id');
        $this->apiKey = $apiKey ?? (string) config('services.remita.api_key');
        $this->serviceTypeId = $serviceTypeId ?? (string) config('services.remita.service_type_id');
    }

    public static function fromCredentials(array $credentials): self
    {
        return new self(
            $credentials['merchant_id'] ?? null,
            $credentials['api_key'] ?? null,
            $credentials['service_type_id'] ?? null
        );
    }

    public function initialize(array $data): array
    {
        try {
            $response = $this->client->post('https://remitademo.net/remita/ecomm/init.reg', [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'merchantId' => $this->merchantId,
                    'serviceTypeId' => $this->serviceTypeId,
                    'amount' => $data['amount'],
                    'responseurl' => $data['callback_url'] ?? config('app.url') . '/api/payments/callback',
                    'orderId' => $data['reference'],
                    'payerName' => $data['customer_name'],
                    'payerEmail' => $data['customer_email'],
                    'payerPhone' => $data['customer_phone'] ?? '',
                    'description' => $data['description'] ?? 'Payment',
                ]
            ]);

            $result = json_decode($response->getBody(), true);

            if ($result['statuscode'] !== '00') {
                throw new \Exception($result['status'] ?? 'Remita initialization failed');
            }

            return [
                'status' => true,
                'rrr' => $result['RRR'],
                'payment_url' => $result['redirecturl'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Remita initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function verify(string $rrr): array
    {
        try {
            $response = $this->client->get("https://remitademo.net/remita/ecomm/{$this->merchantId}/{$rrr}/status.reg", [
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);

            $result = json_decode($response->getBody(), true);

            return [
                'status' => $result['status'] === '00' ? 'success' : 'failed',
                'message' => $result['message'] ?? '',
                'transaction_time' => $result['transactiontime'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Remita verification failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
