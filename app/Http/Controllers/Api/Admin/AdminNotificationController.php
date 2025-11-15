<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAdminNotificationRequest;
use App\Http\Requests\Admin\BulkAdminNotificationRequest;
use App\Http\Resources\Admin\AdminNotificationResource;
use App\Models\Tenant\Notification;
use App\Models\Tenant\User;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminNotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Get all notifications (admin view - can see all users' notifications)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::with(['user:id,first_name,last_name,email,avatar_url']);

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by read status
        if ($request->has('read')) {
            if ($request->read === 'true' || $request->read === true) {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Search by title or message
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $notifications = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'notifications' => AdminNotificationResource::collection($notifications),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'from' => $notifications->firstItem(),
                'to' => $notifications->lastItem(),
            ]
        ]);
    }

    /**
     * Get notification statistics
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total' => Notification::count(),
            'unread' => Notification::whereNull('read_at')->count(),
            'read' => Notification::whereNotNull('read_at')->count(),
            'by_type' => Notification::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
            'today' => Notification::whereDate('created_at', today())->count(),
            'this_week' => Notification::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => Notification::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Get a specific notification
     */
    public function show(Notification $notification): JsonResponse
    {
        $notification->load(['user:id,first_name,last_name,email,avatar_url']);

        return response()->json([
            'success' => true,
            'notification' => new AdminNotificationResource($notification)
        ]);
    }

    /**
     * Create a notification for a specific user
     */
    public function store(CreateAdminNotificationRequest $request): JsonResponse
    {
        try {
            $notification = Notification::create([
                'user_id' => $request->user_id,
                'type' => $request->type,
                'title' => $request->title,
                'message' => $request->message,
                'data' => $request->data ?? [],
                'read_at' => $request->mark_as_read ? now() : null,
            ]);

            // Send notification
            $this->notificationService->sendNotification($notification);

            $notification->load(['user:id,first_name,last_name,email,avatar_url']);

            Log::info('Admin created notification', [
                'admin_id' => Auth::id(),
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification created successfully',
                'notification' => new AdminNotificationResource($notification)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create notification', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send bulk notifications to multiple users
     */
    public function bulkStore(BulkAdminNotificationRequest $request): JsonResponse
    {
        try {
            $notifications = [];
            
            DB::beginTransaction();

            foreach ($request->user_ids as $userId) {
                $notification = Notification::create([
                    'user_id' => $userId,
                    'type' => $request->type,
                    'title' => $request->title,
                    'message' => $request->message,
                    'data' => $request->data ?? [],
                    'read_at' => null,
                ]);

                $this->notificationService->sendNotification($notification);
                $notifications[] = $notification;
            }

            DB::commit();

            Log::info('Admin sent bulk notifications', [
                'admin_id' => Auth::id(),
                'count' => count($notifications),
                'type' => $request->type,
            ]);

            return response()->json([
                'success' => true,
                'message' => count($notifications) . ' notifications sent successfully',
                'notifications' => AdminNotificationResource::collection(collect($notifications)),
                'count' => count($notifications)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to send bulk notifications', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notification to all users or by role
     */
    public function broadcast(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:info,success,warning,error,system',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'data' => 'nullable|array',
            'role' => 'nullable|string|exists:roles,name',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'uuid|exists:users,id',
        ]);

        try {
            // Determine recipients
            if ($request->has('role')) {
                $users = User::role($request->role)->get();
            } elseif ($request->has('user_ids')) {
                $users = User::whereIn('id', $request->user_ids)->get();
            } else {
                // Send to all users
                $users = User::all();
            }

            $notifications = [];
            DB::beginTransaction();

            foreach ($users as $user) {
                $notification = Notification::create([
                    'user_id' => $user->id,
                    'type' => $request->type,
                    'title' => $request->title,
                    'message' => $request->message,
                    'data' => $request->data ?? [],
                    'read_at' => null,
                ]);

                $this->notificationService->sendNotification($notification);
                $notifications[] = $notification;
            }

            DB::commit();

            Log::info('Admin broadcast notification', [
                'admin_id' => Auth::id(),
                'count' => count($notifications),
                'type' => $request->type,
                'role' => $request->role ?? 'all',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification broadcasted to ' . count($notifications) . ' users',
                'count' => count($notifications)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to broadcast notification', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to broadcast notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a notification
     */
    public function update(Request $request, Notification $notification): JsonResponse
    {
        $request->validate([
            'type' => 'sometimes|in:info,success,warning,error,system',
            'title' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'data' => 'nullable|array',
        ]);

        try {
            $notification->update($request->only(['type', 'title', 'message', 'data']));
            $notification->load(['user:id,first_name,last_name,email,avatar_url']);

            Log::info('Admin updated notification', [
                'admin_id' => Auth::id(),
                'notification_id' => $notification->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification updated successfully',
                'notification' => new AdminNotificationResource($notification)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update notification', [
                'error' => $e->getMessage(),
                'notification_id' => $notification->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $notificationId): JsonResponse
    {
        try {
            $notification = Notification::find($notificationId);
            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                ], 404);
            }
            $notification->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'notification' => new AdminNotificationResource($notification->fresh())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark multiple notifications as read
     */
    public function markMultipleAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'uuid|exists:notifications,id',
        ]);

        try {
            $count = Notification::whereIn('id', $request->notification_ids)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => $count . ' notifications marked as read',
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'nullable|uuid|exists:users,id',
        ]);

        try {
            $query = Notification::whereNull('read_at');
            
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $count = $query->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => $count . ' notifications marked as read',
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a notification
     */
    public function destroy(string $notificationId): JsonResponse
    {
        try {
            $notification = Notification::find($notificationId);
            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                ], 404);
            }
            $notification->delete();

            Log::info('Admin deleted notification', [
                'admin_id' => Auth::id(),
                'notification_id' => $notification->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete notification', [
                'error' => $e->getMessage(),
                'notification_id' => $notificationId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete multiple notifications
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'uuid|exists:notifications,id',
        ]);

        try {
            $count = Notification::whereIn('id', $request->notification_ids)->delete();

            Log::info('Admin bulk deleted notifications', [
                'admin_id' => Auth::id(),
                'count' => $count,
            ]);

            return response()->json([
                'success' => true,
                'message' => $count . ' notifications deleted successfully',
                'count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to bulk delete notifications', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $query = Notification::whereNull('read_at');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $count = $query->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count
        ]);
    }

    /**
     * Get notifications by user
     */
    public function getByUser(User $user, Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $user->id);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('read')) {
            if ($request->read === 'true' || $request->read === true) {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
            ],
            'notifications' => AdminNotificationResource::collection($notifications),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ]
        ]);
    }
}

