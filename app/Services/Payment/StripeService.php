<?php

namespace App\Services\Payment;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class StripeService
{
    protected Client $client;
    protected string $secretKey;
    protected string $publicKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->secretKey = config('services.stripe.secret_key');
        $this->publicKey = config('services.stripe.public_key');
    }

    public function createPaymentIntent(array $data): array
    {
        try {
            $response = $this->client->post('https://api.stripe.com/v1/payment_intents', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'form_params' => [
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'metadata' => json_encode($data['metadata'] ?? []),
                ]
            ]);

            $result = json_decode($response->getBody(), true);

            return [
                'id' => $result['id'],
                'client_secret' => $result['client_secret'],
                'status' => $result['status'],
            ];
        } catch (\Exception $e) {
            Log::error('Stripe payment intent creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function verify(string $paymentIntentId): array
    {
        try {
            $response = $this->client->get("https://api.stripe.com/v1/payment_intents/{$paymentIntentId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);

            $result = json_decode($response->getBody(), true);

            return [
                'status' => $result['status'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
            ];
        } catch (\Exception $e) {
            Log::error('Stripe verification failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getBalance(): array
    {
        try {
            $response = $this->client->get('https://api.stripe.com/v1/balance', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('Stripe balance check failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
