<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantProvisioningController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|alpha_dash',
            'custom_domain' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email',
        ]);

        // Create central tenant record
        $tenantId = Str::uuid()->toString();
        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => $tenantId,
            'data' => [
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'custom_domain' => $validated['custom_domain'] ?? null,
                'contact_email' => $validated['contact_email'] ?? null,
                'status' => 'active',
            ],
        ]);

        // Optionally: here you might trigger tenant migrations/seeding using your tenancy setup
        // (left as-is to avoid side effects in this environment)

        return response()->json([
            'success' => true,
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $validated['slug'],
                'custom_domain' => $validated['custom_domain'] ?? null,
                'name' => $validated['name'],
                'status' => 'active',
            ],
        ], 201);
    }
}


