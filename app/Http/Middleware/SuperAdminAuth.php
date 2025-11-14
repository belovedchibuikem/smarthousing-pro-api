<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Log;

class SuperAdminAuth
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
        $token = $request->bearerToken();
        
        if (!$token) {
            Log::info('SuperAdminAuth: No token provided');
            return response()->json(['message' => 'No token provided'], 401);
        }

        Log::info('SuperAdminAuth: Token received', ['token' => substr($token, 0, 10) . '...']);

        $accessToken = PersonalAccessToken::findToken($token);
        
        if (!$accessToken) {
            Log::info('SuperAdminAuth: Token not found in database');
            return response()->json(['message' => 'Invalid token'], 401);
        }
        
        Log::info('SuperAdminAuth: Token found', [
            'tokenable_type' => $accessToken->tokenable_type,
            'tokenable_id' => $accessToken->tokenable_id
        ]);
        
        // Check if the token belongs to a SuperAdmin
        if ($accessToken->tokenable_type !== 'App\\Models\\Central\\SuperAdmin') {
            Log::info('SuperAdminAuth: Invalid token type', ['type' => $accessToken->tokenable_type]);
            return response()->json(['message' => 'Invalid token type'], 401);
        }

        $superAdmin = $accessToken->tokenable;
        
        if (!$superAdmin) {
            Log::info('SuperAdminAuth: Super admin not found');
            return response()->json(['message' => 'Super admin not found'], 401);
        }

        Log::info('SuperAdminAuth: Authentication successful', ['super_admin_id' => $superAdmin->id]);

        $request->setUserResolver(function () use ($superAdmin) {
            return $superAdmin;
        });

        return $next($request);
    }
}
