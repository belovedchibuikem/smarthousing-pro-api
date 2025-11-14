<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class ApiAuthenticate extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$guards
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        try {
            // If no guard specified, use tenant_sanctum by default for tenant API requests
            if (empty($guards)) {
                $guards = ['tenant_sanctum'];
            }
            
            error_log('ApiAuthenticate: Starting with guards: ' . json_encode($guards));
            
            try {
                $this->authenticate($request, $guards);
                error_log('ApiAuthenticate: Authentication successful');
            } catch (\Illuminate\Auth\AuthenticationException $e) {
                error_log('ApiAuthenticate: Authentication failed - ' . $e->getMessage());
                
                // For API requests, return JSON response instead of redirecting
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'Unauthenticated.',
                        'error' => 'Authentication required'
                    ], 401);
                }
                
                // For web requests, redirect to login
                return redirect()->guest(route('login'));
            }

            return $next($request);
        } catch (\Exception $e) {
            error_log('ApiAuthenticate: Unexpected error - ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'message' => 'Authentication middleware error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // For API requests, don't redirect
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }
        
        // For web requests, redirect to login
        return route('login');
    }
}
