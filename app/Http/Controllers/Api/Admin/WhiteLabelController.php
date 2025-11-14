<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WhiteLabelRequest;
use App\Http\Resources\Admin\WhiteLabelResource;
use App\Models\Tenant\WhiteLabelSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhiteLabelController extends Controller
{
    public function index(): JsonResponse
    {
        $origin = request()->header('Origin');
        $tenantId = tenant('id');
        
        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant context not available',
                'error' => 'Tenant not initialized'
            ], 500)->withHeaders([
                'Access-Control-Allow-Origin' => $origin ?? '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }
        
        $settings = WhiteLabelSetting::where('tenant_id', $tenantId)->first();

        if (!$settings) {
            // Create default white label settings
            $settings = WhiteLabelSetting::create([
                'tenant_id' => $tenantId,
                'brand_name' => tenant('name') ?? 'Default Brand',
                'company_name' => tenant('name') ?? 'Default Company',
                'primary_color' => '#3b82f6',
                'secondary_color' => '#8b5cf6',
                'accent_color' => '#10b981',
                'background_color' => '#ffffff',
                'text_color' => '#1f2937',
                'font_family' => 'Inter',
                'heading_font' => 'Inter',
                'body_font' => 'Inter',
                'enabled_modules' => ['properties', 'loans', 'investments', 'contributions', 'wallet'],
                'is_active' => true,
            ]);
        }

        return response()->json([
            'settings' => new WhiteLabelResource($settings)
        ])->withHeaders([
            'Access-Control-Allow-Origin' => $origin ?? '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = \Validator::make($request->all(), (new WhiteLabelRequest())->rules());
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        // Map company_name to brand_name if brand_name is not provided (for backward compatibility)
        if (isset($data['company_name']) && empty($data['brand_name'])) {
            $data['brand_name'] = $data['company_name'];
        }
        // Map brand_name to company_name if company_name is not provided
        if (isset($data['brand_name']) && empty($data['company_name'])) {
            $data['company_name'] = $data['brand_name'];
        }

        $settings = WhiteLabelSetting::updateOrCreate(
            ['tenant_id' => tenant('id')],
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'White label settings saved successfully',
            'settings' => new WhiteLabelResource($settings->fresh())
        ]);
    }

    public function update(Request $request, WhiteLabelSetting $settings): JsonResponse
    {
        // Ensure the settings belong to the current tenant
        if ($settings->tenant_id !== tenant('id')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = \Validator::make($request->all(), (new WhiteLabelRequest())->rules());
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        // Map company_name to brand_name if brand_name is not provided (for backward compatibility)
        if (isset($data['company_name']) && empty($data['brand_name'])) {
            $data['brand_name'] = $data['company_name'];
        }
        // Map brand_name to company_name if company_name is not provided
        if (isset($data['brand_name']) && empty($data['company_name'])) {
            $data['company_name'] = $data['brand_name'];
        }

        $settings->update($data);

        return response()->json([
            'success' => true,
            'message' => 'White label settings updated successfully',
            'settings' => new WhiteLabelResource($settings->fresh())
        ]);
    }

    public function toggle(): JsonResponse
    {
        $settings = WhiteLabelSetting::where('tenant_id', tenant('id'))->first();
        
        if (!$settings) {
            return response()->json([
                'message' => 'White label settings not found'
            ], 404);
        }

        $settings->update(['is_active' => !$settings->is_active]);

        return response()->json([
            'success' => true,
            'message' => $settings->is_active ? 'White label enabled' : 'White label disabled',
            'settings' => new WhiteLabelResource($settings)
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,svg|max:2048'
        ]);

        $settings = WhiteLabelSetting::where('tenant_id', tenant('id'))->first();
        
        if (!$settings) {
            return response()->json([
                'message' => 'White label settings not found'
            ], 404);
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('white-label/logos', 'public');
            $settings->update(['logo_url' => $path]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logo uploaded successfully',
            'logo_url' => asset('storage/' . $settings->logo_url)
        ]);
    }

    public function uploadFavicon(Request $request): JsonResponse
    {
        $request->validate([
            'favicon' => 'required|image|mimes:ico,png|max:512'
        ]);

        $settings = WhiteLabelSetting::where('tenant_id', tenant('id'))->first();
        
        if (!$settings) {
            return response()->json([
                'success' => false,
                'message' => 'White label settings not found'
            ], 404);
        }

        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('white-label/favicons', 'public');
            $settings->update(['favicon_url' => $path]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Favicon uploaded successfully',
            'favicon_url' => asset('storage/' . $settings->favicon_url)
        ]);
    }

    public function uploadLogoDark(Request $request): JsonResponse
    {
        $request->validate([
            'logo_dark' => 'required|image|mimes:jpeg,png,jpg,svg|max:2048'
        ]);

        $settings = WhiteLabelSetting::where('tenant_id', tenant('id'))->firstOrFail();

        if ($request->hasFile('logo_dark')) {
            $path = $request->file('logo_dark')->store('white-label/logos', 'public');
            $settings->update(['logo_dark_url' => $path]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dark logo uploaded successfully',
            'logo_dark_url' => asset('storage/' . $settings->logo_dark_url)
        ]);
    }

    public function uploadLoginBackground(Request $request): JsonResponse
    {
        $request->validate([
            'login_background' => 'required|image|mimes:jpeg,png,jpg|max:5120'
        ]);

        $settings = WhiteLabelSetting::where('tenant_id', tenant('id'))->firstOrFail();

        if ($request->hasFile('login_background')) {
            $path = $request->file('login_background')->store('white-label/images', 'public');
            $settings->update(['login_background_url' => $path]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login background uploaded successfully',
            'login_background_url' => asset('storage/' . $settings->login_background_url)
        ]);
    }

    public function uploadDashboardHero(Request $request): JsonResponse
    {
        $request->validate([
            'dashboard_hero' => 'required|image|mimes:jpeg,png,jpg|max:5120'
        ]);

        $settings = WhiteLabelSetting::where('tenant_id', tenant('id'))->firstOrFail();

        if ($request->hasFile('dashboard_hero')) {
            $path = $request->file('dashboard_hero')->store('white-label/images', 'public');
            $settings->update(['dashboard_hero_url' => $path]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dashboard hero image uploaded successfully',
            'dashboard_hero_url' => asset('storage/' . $settings->dashboard_hero_url)
        ]);
    }

    public function uploadEmailLogo(Request $request): JsonResponse
    {
        $request->validate([
            'email_logo' => 'required|image|mimes:jpeg,png,jpg,svg|max:1024'
        ]);

        $settings = WhiteLabelSetting::where('tenant_id', tenant('id'))->firstOrFail();

        if ($request->hasFile('email_logo')) {
            $path = $request->file('email_logo')->store('white-label/logos', 'public');
            $settings->update(['email_logo_url' => $path]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email logo uploaded successfully',
            'email_logo_url' => asset('storage/' . $settings->email_logo_url)
        ]);
    }
}
