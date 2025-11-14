<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Log;

class TenantAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = $request->bearerToken();
            
            if (!$token) {
                Log::info('TenantAuth: No token provided');
                return response()->json(['message' => 'No token provided'], 401);
            }

            Log::info('TenantAuth: Starting', ['token_prefix' => substr($token, 0, 20)]);

            // Use PersonalAccessToken model which will use the tenant connection
            // because User model has protected $connection = 'tenant'
            $accessToken = PersonalAccessToken::findToken($token);
            
            if (!$accessToken) {
                Log::info('TenantAuth: Token not found');
                return response()->json(['message' => 'Invalid token'], 401);
            }
            
            Log::info('TenantAuth: Token found', [
                'tokenable_type' => $accessToken->tokenable_type,
                'tokenable_id' => $accessToken->tokenable_id
            ]);
            
            // Check if the token belongs to a Tenant User
            if ($accessToken->tokenable_type !== 'App\\Models\\Tenant\\User') {
                Log::info('TenantAuth: Invalid token type', ['type' => $accessToken->tokenable_type]);
                return response()->json(['message' => 'Invalid token type'], 401);
            }

            // Get the user
            $user = $accessToken->tokenable;
            
            if (!$user) {
                Log::info('TenantAuth: User not found for tokenable');
                return response()->json(['message' => 'User not found'], 401);
            }

            Log::info('TenantAuth: Authentication successful', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);

            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            return $next($request);
        } catch (\Exception $e) {
            Log::error('TenantAuth: Exception', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'message' => 'Authentication error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
