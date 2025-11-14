<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Enable CORS middleware globally
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        
        // Web middleware configuration
        $middleware->web(append: [
            \App\Http\Middleware\VerifyCsrfToken::class,
        ]);
        
        // Middleware aliases
        $middleware->alias([
            'auth' => \App\Http\Middleware\ApiAuthenticate::class,
            'auth:sanctum' => \App\Http\Middleware\ApiAuthenticate::class,
            'auth:tenant_sanctum' => \App\Http\Middleware\ApiAuthenticate::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
            'tenant_auth' => \App\Http\Middleware\TenantAuth::class,
            'super_admin_auth' => \App\Http\Middleware\SuperAdminAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
