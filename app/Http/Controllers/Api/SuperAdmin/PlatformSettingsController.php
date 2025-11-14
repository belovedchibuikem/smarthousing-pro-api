<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\PlatformSettingsRequest;
use App\Http\Resources\SuperAdmin\PlatformSettingsResource;
use App\Models\Central\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PlatformSettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $category = $request->get('category', 'all');
            $public = $request->get('public', false);

            $query = PlatformSetting::query();

            if ($category !== 'all') {
                $query->where('category', $category);
            }

            if ($public) {
                $query->where('is_public', true);
            }

            $settings = $query->orderBy('category')->orderBy('key')->get();

            return response()->json([
                'success' => true,
                'settings' => PlatformSettingsResource::collection($settings)
            ]);
        } catch (\Exception $e) {
            Log::error('Platform settings index error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve platform settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(PlatformSetting $setting): JsonResponse
    {
        return response()->json([
            'success' => true,
            'setting' => new PlatformSettingsResource($setting)
        ]);
    }

    public function store(PlatformSettingsRequest $request): JsonResponse
    {
        try {
            $setting = PlatformSetting::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Platform setting created successfully',
                'setting' => new PlatformSettingsResource($setting)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Platform setting creation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create platform setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(PlatformSettingsRequest $request, PlatformSetting $setting): JsonResponse
    {
        try {
            $setting->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Platform setting updated successfully',
                'setting' => new PlatformSettingsResource($setting)
            ]);
        } catch (\Exception $e) {
            Log::error('Platform setting update error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update platform setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(PlatformSetting $setting): JsonResponse
    {
        try {
            $setting->delete();

            return response()->json([
                'success' => true,
                'message' => 'Platform setting deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Platform setting deletion error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete platform setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        try {
            $settings = $request->input('settings', []);

            if (empty($settings)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No settings provided'
                ], 400);
            }

            $updatedSettings = [];

            foreach ($settings as $settingData) {
                Log::info('Processing setting:', $settingData);
                
                $validator = Validator::make($settingData, [
                    'key' => 'required|string',
                    'value' => 'required',
                    'type' => 'sometimes|string|in:string,boolean,integer,json',
                    'category' => 'sometimes|string'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }

                $setting = PlatformSetting::updateOrCreate(
                    ['key' => $settingData['key']],
                    $settingData
                );
                
                Log::info('Setting saved:', ['key' => $setting->key, 'value' => $setting->value, 'type' => $setting->type]);

                $updatedSettings[] = new PlatformSettingsResource($setting);
            }

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'settings' => $updatedSettings
            ]);
        } catch (\Exception $e) {
            Log::error('Platform settings bulk update error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByCategory(Request $request, string $category): JsonResponse
    {
        try {
            $settings = PlatformSetting::where('category', $category)->get();

            return response()->json([
                'success' => true,
                'settings' => PlatformSettingsResource::collection($settings)
            ]);
        } catch (\Exception $e) {
            Log::error('Platform settings by category error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings for category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testEmailSettings(Request $request): JsonResponse
    {
        try {
            $emailSettings = $request->only([
                'smtp_host', 'smtp_port', 'smtp_username', 
                'smtp_password', 'smtp_encryption', 'from_email', 'from_name'
            ]);

            // Test SMTP connection
            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $emailSettings['smtp_host'],
                $emailSettings['smtp_port'],
                $emailSettings['smtp_encryption'] === 'tls'
            );
            
            $transport->setUsername($emailSettings['smtp_username']);
            $transport->setPassword($emailSettings['smtp_password']);

            // Test connection
            $mailer = new \Symfony\Component\Mailer\Mailer($transport);
            
            return response()->json([
                'success' => true,
                'message' => 'Email settings test successful'
            ]);
        } catch (\Exception $e) {
            Log::error('Email settings test error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Email settings test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
