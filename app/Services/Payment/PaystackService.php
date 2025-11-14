<?php

namespace App\Services\Payment;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected Client $client;
    protected string $secretKey;
    protected string $publicKey;
    protected bool $simulate;

    public function __construct(
        ?string $secretKey = null,
        ?string $publicKey = null,
        ?Client $client = null,
        ?bool $verify = null,
        ?bool $simulate = null
    ) {
        $shouldVerify = $verify ?? !app()->environment('local');
        $this->simulate = $simulate ?? (bool) config('services.paystack.simulate_local', app()->environment('local'));

        $this->client = $client ?? new Client([
            'verify' => $shouldVerify,
        ]);
        $this->secretKey = $secretKey ?? (string) config('services.paystack.secret_key');
        $this->publicKey = $publicKey ?? (string) config('services.paystack.public_key');
    }

    public static function fromCredentials(array $credentials): self
    {
        return new self(
            $credentials['secret_key'] ?? null,
            $credentials['public_key'] ?? null,
            null,
            $credentials['verify'] ?? null,
            $credentials['simulate'] ?? null
        );
    }

    public function initialize(array $data): array
    {
        if ($this->simulate) {
            $reference = $data['reference'] ?? ('SIM_' . uniqid());

            $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');

            return [
                'status' => true,
                'message' => 'Simulated Paystack initialization',
                'data' => [
                    'authorization_url' => "{$baseUrl}/simulated/paystack/{$reference}",
                    'reference' => $reference,
                ],
            ];
        }

        try {
            $response = $this->client->post('https://api.paystack.co/transaction/initialize', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => $data
            ]);

            $result = json_decode($response->getBody(), true);

            if (!$result['status']) {
                throw new \Exception($result['message'] ?? 'Paystack initialization failed');
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Paystack initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function verify(string $reference): array
    {
        if ($this->simulate) {
            return [
                'status' => true,
                'message' => 'Simulated Paystack verification',
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => 0,
                ],
            ];
        }

        try {
            $response = $this->client->get("https://api.paystack.co/transaction/verify/{$reference}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/json'
                ]
            ]);

            $result = json_decode($response->getBody(), true);

            if (!$result['status']) {
                throw new \Exception($result['message'] ?? 'Paystack verification failed');
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Paystack verification failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getBalance(): array
    {
        try {
            $response = $this->client->get('https://api.paystack.co/balance', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/json'
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('Paystack balance check failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function findCustomerByEmail(string $email): ?array
    {
        try {
            $response = $this->client->get('https://api.paystack.co/customer', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'email' => $email,
                ],
            ]);

            $result = json_decode($response->getBody(), true);
            if (!($result['status'] ?? false)) {
                return null;
            }

            $data = $result['data'] ?? [];
            if (is_array($data) && count($data) > 0) {
                return $data[0];
            }

            return null;
        } catch (ClientException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    public function createCustomer(array $payload): array
    {
        try {
            $response = $this->client->post('https://api.paystack.co/customer', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $result = json_decode($response->getBody(), true);
            if (!($result['status'] ?? false)) {
                throw new \Exception($result['message'] ?? 'Unable to create Paystack customer');
            }

            return $result['data'] ?? [];
        } catch (ClientException $e) {
            $body = (string) $e->getResponse()?->getBody();
            Log::warning('Paystack create customer failed', [
                'payload' => $payload,
                'error' => $body,
            ]);
            throw $e;
        }
    }

    public function createDedicatedAccount(array $payload): array
    {
        try {
            $response = $this->client->post('https://api.paystack.co/dedicated_account', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $result = json_decode($response->getBody(), true);
            if (!($result['status'] ?? false)) {
                throw new \Exception($result['message'] ?? 'Unable to create Paystack dedicated account');
            }

            return $result['data'] ?? [];
        } catch (ClientException $e) {
            $body = (string) $e->getResponse()?->getBody();
            Log::warning('Paystack create dedicated account failed', [
                'payload' => $payload,
                'error' => $body,
            ]);
            throw $e;
        }
    }

    public function listDedicatedAccounts(array $query): array
    {
        try {
            $response = $this->client->get('https://api.paystack.co/dedicated_account', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => $query,
            ]);

            $result = json_decode($response->getBody(), true);
            if (!($result['status'] ?? false)) {
                throw new \Exception($result['message'] ?? 'Unable to list Paystack dedicated accounts');
            }

            return $result['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('Paystack list dedicated accounts failed: ' . $e->getMessage(), [
                'query' => $query,
            ]);
            throw $e;
        }
    }
}
