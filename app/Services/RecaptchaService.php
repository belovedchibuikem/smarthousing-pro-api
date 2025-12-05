<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    protected string $secretKey;
    protected string $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct()
    {
        $this->secretKey = config('services.recaptcha.secret_key', '');
    }

    /**
     * Check if reCAPTCHA is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        // Check if explicitly disabled via config
        if (config('services.recaptcha.enabled', true) === false) {
            return false;
        }

        // Skip in local/development environments if configured
        $skipInLocal = config('services.recaptcha.skip_in_local', true);
        if ($skipInLocal && in_array(config('app.env'), ['local', 'development'])) {
            return false;
        }

        // Skip if secret key is not configured
        if (empty($this->secretKey)) {
            return false;
        }

        return true;
    }

    /**
     * Verify reCAPTCHA token
     *
     * @param string|null $token
     * @param string|null $remoteIp
     * @return bool
     */
    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        // Skip verification if reCAPTCHA is disabled
        if (!$this->isEnabled()) {
            Log::info('reCAPTCHA verification skipped (disabled or development mode)');
            return true; // Allow requests when disabled
        }

        // Accept dummy token ONLY in development/local environments
        // This prevents security issues in production
        if ($token === 'dev-token-disabled') {
            $isLocalEnv = in_array(config('app.env'), ['local', 'development']);
            if ($isLocalEnv) {
                Log::info('reCAPTCHA dummy token accepted (development mode)');
                return true;
            } else {
                Log::warning('reCAPTCHA dummy token rejected (production environment)');
                return false;
            }
        }

        if (empty($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->post($this->verifyUrl, [
                'secret' => $this->secretKey,
                'response' => $token,
                'remoteip' => $remoteIp,
            ]);

            if (!$response->successful()) {
                Log::error('reCAPTCHA verification request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $data = $response->json();

            if (!isset($data['success']) || $data['success'] !== true) {
                Log::warning('reCAPTCHA verification failed', [
                    'errors' => $data['error-codes'] ?? [],
                ]);
                return false;
            }

            // Check score for reCAPTCHA v3 (required for v3)
            // Score ranges from 0.0 (bot) to 1.0 (human)
            // Typical threshold is 0.5, but can be adjusted based on your needs
            if (isset($data['score'])) {
                $score = (float) $data['score'];
                $threshold = config('services.recaptcha.score_threshold', 0.5);
                
                if ($score < $threshold) {
                    Log::warning('reCAPTCHA score too low', [
                        'score' => $score,
                        'threshold' => $threshold,
                    ]);
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('reCAPTCHA verification exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Get reCAPTCHA site key for frontend
     *
     * @return string
     */
    public function getSiteKey(): string
    {
        return config('services.recaptcha.site_key', '');
    }
}

