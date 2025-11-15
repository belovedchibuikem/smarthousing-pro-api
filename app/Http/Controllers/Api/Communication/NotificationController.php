<?php

namespace App\Http\Controllers\Api\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\CreateNotificationRequest;
use App\Http\Resources\Communication\NotificationResource;
use App\Models\Tenant\Notification;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        
        $query = Notification::where('user_id', $userId)
            ->select(['id', 'user_id', 'type', 'title', 'message', 'data', 'read_at', 'created_at', 'updated_at']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by read status
        if ($request->has('read')) {
            if ($request->read) {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'notifications' => NotificationResource::collection($notifications),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ]
        ]);
    }

    public function store(CreateNotificationRequest $request): JsonResponse
    {
        $notification = Notification::create([
            'user_id' => $request->user_id,
            'type' => $request->type,
            'title' => $request->title,
            'message' => $request->message,
            'data' => $request->data ?? [],
            'read_at' => null,
        ]);

        // Send notification
        $this->notificationService->sendNotification($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification sent successfully',
            'notification' => new NotificationResource($notification)
        ], 201);
    }

    public function show(Request $request, string $notificationId): JsonResponse
    {
        $userId = $request->user()->id;
        
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->select(['id', 'user_id', 'type', 'title', 'message', 'data', 'read_at', 'created_at', 'updated_at'])
            ->first();
            
        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        return response()->json([
            'notification' => new NotificationResource($notification)
        ]);
    }

    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        $userId = $request->user()->id;
        
        $updated = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
            
        if (!$updated) {
            return response()->json(['message' => 'Notification not found or already read'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        
        $count = Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'unread_count' => $count
        ]);
    }

    public function destroy(Request $request, string $notificationId): JsonResponse
    {
        $userId = $request->user()->id;
        
        $deleted = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->delete();
            
        if (!$deleted) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    }
}
