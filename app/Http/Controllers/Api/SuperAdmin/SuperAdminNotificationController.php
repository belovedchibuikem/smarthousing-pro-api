<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\SuperAdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SuperAdminNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            Log::info('SuperAdminNotificationController::index - Starting notifications request');
            
            // Try multiple ways to get the authenticated user ID
            $userId = $request->user()?->id;
            if (!$userId) {
                $userId = $request->user()?->id;
            }
            if (!$userId) {
                $userId = $request->user()?->id;
            }
            
            if (!$userId) {
                Log::error('SuperAdminNotificationController::index - No authenticated user found');
                return response()->json([
                    'success' => false,
                    'message' => 'No authenticated user found'
                ], 401);
            }
            
            Log::info('SuperAdminNotificationController::index - User ID found', ['user_id' => $userId]);
            
            $query = SuperAdminNotification::where('super_admin_id', $userId);

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter by read status
            if ($request->has('read')) {
                $query->where('read_at', $request->read ? '!=' : '=', null);
            }

            $notifications = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            Log::info('SuperAdminNotificationController::index - Notifications retrieved', [
                'count' => $notifications->count(),
                'total' => $notifications->total()
            ]);

            return response()->json([
                'success' => true,
                'notifications' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('SuperAdminNotificationController::index - Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, SuperAdminNotification $notification): JsonResponse
    {
        try {
            Log::info('SuperAdminNotificationController::show - Starting notification show request', [
                'notification_id' => $notification->id
            ]);
            
            // Try multiple ways to get the authenticated user ID
            $userId = $request->user()?->id;
            if (!$userId) {
                $userId = Auth::guard('super_admin')->id();
            }
            if (!$userId) {
                $userId = $request->user()?->id;
            }
            
            if (!$userId) {
                Log::error('SuperAdminNotificationController::show - No authenticated user found');
                return response()->json([
                    'success' => false,
                    'message' => 'No authenticated user found'
                ], 401);
            }
            
            if ($notification->super_admin_id !== $userId) {
                Log::warning('SuperAdminNotificationController::show - Unauthorized access attempt', [
                    'user_id' => $userId,
                    'notification_super_admin_id' => $notification->super_admin_id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            Log::info('SuperAdminNotificationController::show - Notification retrieved successfully');
            
            return response()->json([
                'success' => true,
                'notification' => $notification
            ]);
            
        } catch (\Exception $e) {
            Log::error('SuperAdminNotificationController::show - Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead(Request $request, SuperAdminNotification $notification): JsonResponse
    {
        try {
            Log::info('SuperAdminNotificationController::markAsRead - Starting mark as read request', [
                'notification_id' => $notification->id
            ]);
            
            // Try multiple ways to get the authenticated user ID
            $userId = $request->user()?->id;
            if (!$userId) {
                $userId = Auth::guard('super_admin')->id();
            }
            if (!$userId) {
                $userId = Auth::id();
            }
            
            if (!$userId) {
                Log::error('SuperAdminNotificationController::markAsRead - No authenticated user found');
                return response()->json([
                    'success' => false,
                    'message' => 'No authenticated user found'
                ], 401);
            }
            
            if ($notification->super_admin_id !== $userId) {
                Log::warning('SuperAdminNotificationController::markAsRead - Unauthorized access attempt', [
                    'user_id' => $userId,
                    'notification_super_admin_id' => $notification->super_admin_id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $notification->update(['read_at' => now()]);
            
            Log::info('SuperAdminNotificationController::markAsRead - Notification marked as read successfully');

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
            
        } catch (\Exception $e) {
            Log::error('SuperAdminNotificationController::markAsRead - Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while marking notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            Log::info('SuperAdminNotificationController::markAllAsRead - Starting mark all as read request');
            
            // Try multiple ways to get the authenticated user ID
            $userId = $request->user()?->id;
            if (!$userId) {
                $userId = Auth::guard('super_admin')->id();
            }
            if (!$userId) {
                $userId = Auth::id();
            }
            
            if (!$userId) {
                Log::error('SuperAdminNotificationController::markAllAsRead - No authenticated user found');
                return response()->json([
                    'success' => false,
                    'message' => 'No authenticated user found'
                ], 401);
            }
            
            $updated = SuperAdminNotification::where('super_admin_id', $userId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
                
            Log::info('SuperAdminNotificationController::markAllAsRead - Notifications marked as read', [
                'updated_count' => $updated
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
            
        } catch (\Exception $e) {
            Log::error('SuperAdminNotificationController::markAllAsRead - Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while marking all notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function unreadCount(Request $request): JsonResponse
    {
        try {
            Log::info('SuperAdminNotificationController::unreadCount - Starting unread count request');
            
            // Try multiple ways to get the authenticated user ID
            $userId = $request->user()?->id;
            if (!$userId) {
                $userId = Auth::guard('super_admin')->id();
            }
            if (!$userId) {
                $userId = Auth::id();
            }
            
            if (!$userId) {
                Log::error('SuperAdminNotificationController::unreadCount - No authenticated user found');
                return response()->json([
                    'success' => false,
                    'message' => 'No authenticated user found'
                ], 401);
            }
            
            $count = SuperAdminNotification::where('super_admin_id', $userId)
                ->whereNull('read_at')
                ->count();
                
            Log::info('SuperAdminNotificationController::unreadCount - Unread count retrieved', [
                'unread_count' => $count
            ]);

            return response()->json([
                'success' => true,
                'unread_count' => $count
            ]);
            
        } catch (\Exception $e) {
            Log::error('SuperAdminNotificationController::unreadCount - Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while getting unread count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, SuperAdminNotification $notification): JsonResponse
    {
        try {
            Log::info('SuperAdminNotificationController::destroy - Starting notification delete request', [
                'notification_id' => $notification->id
            ]);
            
            // Try multiple ways to get the authenticated user ID
            $userId = $request->user()?->id;
            if (!$userId) {
                $userId = Auth::guard('super_admin')->id();
            }
            if (!$userId) {
                $userId = Auth::id();
            }
            
            if (!$userId) {
                Log::error('SuperAdminNotificationController::destroy - No authenticated user found');
                return response()->json([
                    'success' => false,
                    'message' => 'No authenticated user found'
                ], 401);
            }
            
            if ($notification->super_admin_id !== $userId) {
                Log::warning('SuperAdminNotificationController::destroy - Unauthorized access attempt', [
                    'user_id' => $userId,
                    'notification_super_admin_id' => $notification->super_admin_id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $notification->delete();
            
            Log::info('SuperAdminNotificationController::destroy - Notification deleted successfully');

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('SuperAdminNotificationController::destroy - Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
