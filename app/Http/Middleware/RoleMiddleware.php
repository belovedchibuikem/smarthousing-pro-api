<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        try {
            Log::info('RoleMiddleware: Starting', ['required_role' => $role]);
            
            $user = $request->user();
            
            if (!$user) {
                Log::warning('RoleMiddleware: User not authenticated');
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            
            Log::info('RoleMiddleware: User authenticated', [
                'user_id' => $user->id,
                'roles' => $user->getAllRoles(),
            ]);
            
            // Check if user has the required role using both legacy and Spatie permissions
            $hasRole = $user->hasAnyRoleLegacy([$role]);
            
            if (!$hasRole) {
                Log::warning('RoleMiddleware: Access denied', [
                    'user_roles' => $user->getAllRoles(),
                    'required' => $role,
                ]);
                return response()->json([
                    'message' => 'Forbidden', 
                    'user_roles' => $user->getAllRoles(), 
                    'required_role' => $role
                ], 403);
            }
            
            Log::info('RoleMiddleware: Access granted');
            return $next($request);
        } catch (\Exception $e) {
            Log::error('RoleMiddleware: Exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Middleware error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
