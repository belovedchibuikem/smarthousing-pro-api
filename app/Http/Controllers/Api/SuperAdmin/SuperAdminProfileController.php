<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\SuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SuperAdminProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        try {
            Log::info('SuperAdminProfileController::show - Starting profile show request');
            
            // Try multiple ways to get the authenticated user
            Log::info('SuperAdminProfileController::show - Attempting to get user');
            $user = $request->user();
            Log::info('SuperAdminProfileController::show - Request user result', ['user' => $user ? 'found' : 'null']);
            
            if (!$user) {
                $user = Auth::guard('super_admin')->user();
                Log::info('SuperAdminProfileController::show - Auth guard user result', ['user' => $user ? 'found' : 'null']);
            }
            if (!$user) {
                $user = Auth::user();
                Log::info('SuperAdminProfileController::show - Auth user result', ['user' => $user ? 'found' : 'null']);
            }
            
            if (!$user) {
                Log::error('SuperAdminProfileController::show - No authenticated user found');
                return response()->json([
                    'success' => false,
                    'message' => 'No authenticated user found'
                ], 401);
            }
            
            Log::info('SuperAdminProfileController::show - User found', [
                'user_id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone ?? 'null'
            ]);
            
            $response = [
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->is_active ? 'active' : 'inactive',
                    'last_login' => $user->last_login,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ];
            
            Log::info('SuperAdminProfileController::show - Response prepared', ['response' => $response]);
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            Log::error('SuperAdminProfileController::show - Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request): JsonResponse
    {
        try {
            Log::info('SuperAdminProfileController::update - Starting profile update request', [
                'request_data' => $request->all()
            ]);
            
            // Try multiple ways to get the authenticated user
            $user = $request->user();
            if (!$user) {
                $user = Auth::guard('super_admin')->user();
            }
            if (!$user) {
                $user = Auth::user();
            }
            
            if (!$user) {
                Log::error('SuperAdminProfileController::update - No authenticated user found');
                return response()->json([
                    'success' => false,
                    'message' => 'No authenticated user found'
                ], 401);
            }
            
            Log::info('SuperAdminProfileController::update - User found', [
                'user_id' => $user->id,
                'current_email' => $user->email
            ]);
            
            // Handle different data formats from frontend
            $requestData = $request->all();
            
            // Check if data is sent as JSON string in array (frontend issue)
            if (isset($requestData[0]) && is_string($requestData[0])) {
                Log::info('SuperAdminProfileController::update - Detected JSON string format, parsing...');
                $jsonData = json_decode($requestData[0], true);
                if ($jsonData) {
                    $requestData = $jsonData;
                    Log::info('SuperAdminProfileController::update - Parsed JSON data', ['parsed_data' => $requestData]);
                }
            }
            
            $validator = Validator::make($requestData, [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:super_admins,email,' . $user->id,
                'phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                Log::warning('SuperAdminProfileController::update - Validation failed', [
                    'errors' => $validator->errors()->toArray()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            Log::info('SuperAdminProfileController::update - Validation passed, updating user');
            
            $user->first_name = $requestData['first_name'];
            $user->last_name = $requestData['last_name'];
            $user->email = $requestData['email'];
            $user->phone = $requestData['phone'] ?? null;
            
            Log::info('SuperAdminProfileController::update - User data updated, saving to database');
            $user->save();
            
            Log::info('SuperAdminProfileController::update - User saved successfully');

            $response = [
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->is_active ? 'active' : 'inactive',
                    'last_login' => $user->last_login,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ];
            
            Log::info('SuperAdminProfileController::update - Response prepared', ['response' => $response]);
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            Log::error('SuperAdminProfileController::update - Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        try {
            Log::info('SuperAdminProfileController::changePassword - Starting password change request');
            
            // Try multiple ways to get the authenticated user
            $user = $request->user();
            if (!$user) {
                $user = Auth::guard('super_admin')->user();
            }
            if (!$user) {
                $user = Auth::user();
            }
            
            if (!$user) {
                Log::error('SuperAdminProfileController::changePassword - No authenticated user found');
                return response()->json([
                    'success' => false,
                    'message' => 'No authenticated user found'
                ], 401);
            }
            
            Log::info('SuperAdminProfileController::changePassword - User found', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                Log::warning('SuperAdminProfileController::changePassword - Validation failed', [
                    'errors' => $validator->errors()->toArray()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            Log::info('SuperAdminProfileController::changePassword - Validation passed, checking current password');
            
            // Check current password
            if (!Hash::check($request->current_password, $user->password)) {
                Log::warning('SuperAdminProfileController::changePassword - Current password is incorrect');
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            Log::info('SuperAdminProfileController::changePassword - Current password verified, updating password');
            
            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();
            
            Log::info('SuperAdminProfileController::changePassword - Password updated successfully');

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('SuperAdminProfileController::changePassword - Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while changing password',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
