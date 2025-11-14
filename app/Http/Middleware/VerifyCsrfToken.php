<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/*',
        'api/auth/*',
        'api/register',
        'api/login',
        'api/verify-otp',
        'api/resend-otp',
        'api/forgot-password',
        'api/reset-password',
        'api/refresh',
        'api/logout',
    ];

    /**
     * Get the excluded routes for testing purposes.
     */
    public function getExcludedRoutes(): array
    {
        return $this->except;
    }
}
