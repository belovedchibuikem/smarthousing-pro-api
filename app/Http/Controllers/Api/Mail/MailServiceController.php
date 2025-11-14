<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mail\ComposeMailRequest;
use App\Http\Requests\Mail\ReplyMailRequest;
use App\Http\Resources\Mail\MailResource;
use App\Models\Tenant\Mail;
use App\Models\Tenant\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MailServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $folder = $request->get('folder', 'inbox');

        $query = Mail::where(function($q) use ($user, $folder) {
            switch ($folder) {
                case 'inbox':
                    $q->where('recipient_id', $user->id);
                    break;
                case 'sent':
                    $q->where('sender_id', $user->id);
                    break;
                case 'drafts':
                    $q->where('sender_id', $user->id)->where('status', 'draft');
                    break;
                case 'trash':
                    $q->where(function($subQ) use ($user) {
                        $subQ->where('sender_id', $user->id)
                             ->orWhere('recipient_id', $user->id);
                    })->where('status', 'deleted');
                    break;
            }
        });

        // Filter by read status
        if ($request->has('unread_only') && $request->unread_only) {
            $query->where('is_read', false);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $mails = $query->with(['sender', 'recipient'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'mails' => MailResource::collection($mails),
            'pagination' => [
                'current_page' => $mails->currentPage(),
                'last_page' => $mails->lastPage(),
                'per_page' => $mails->perPage(),
                'total' => $mails->total(),
            ]
        ]);
    }

    public function compose(ComposeMailRequest $request): JsonResponse
    {
        $user = Auth::user();

        $mail = Mail::create([
            'sender_id' => $user->id,
            'recipient_id' => $request->recipient_id,
            'subject' => $request->subject,
            'body' => $request->body,
            'status' => $request->status ?? 'sent',
            'priority' => $request->priority ?? 'normal',
            'is_read' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mail sent successfully',
            'mail' => new MailResource($mail->load(['sender', 'recipient']))
        ], 201);
    }

    public function show(Mail $mail): JsonResponse
    {
        $user = Auth::user();

        // Check if user can view this mail
        if ($mail->sender_id !== $user->id && $mail->recipient_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Mark as read if user is recipient
        if ($mail->recipient_id === $user->id && !$mail->is_read) {
            $mail->update(['is_read' => true, 'read_at' => now()]);
        }

        $mail->load(['sender', 'recipient', 'replies']);

        return response()->json([
            'mail' => new MailResource($mail)
        ]);
    }

    public function reply(ReplyMailRequest $request, Mail $mail): JsonResponse
    {
        $user = Auth::user();

        // Check if user can reply to this mail
        if ($mail->sender_id !== $user->id && $mail->recipient_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $reply = Mail::create([
            'sender_id' => $user->id,
            'recipient_id' => $mail->sender_id === $user->id ? $mail->recipient_id : $mail->sender_id,
            'subject' => 'Re: ' . $mail->subject,
            'body' => $request->body,
            'status' => 'sent',
            'priority' => $mail->priority,
            'is_read' => false,
            'parent_id' => $mail->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reply sent successfully',
            'reply' => new MailResource($reply->load(['sender', 'recipient']))
        ]);
    }

    public function markAsRead(Mail $mail): JsonResponse
    {
        $user = Auth::user();

        if ($mail->recipient_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mail->update([
            'is_read' => true,
            'read_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mail marked as read'
        ]);
    }

    public function markAsUnread(Mail $mail): JsonResponse
    {
        $user = Auth::user();

        if ($mail->recipient_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mail->update([
            'is_read' => false,
            'read_at' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mail marked as unread'
        ]);
    }

    public function moveToTrash(Mail $mail): JsonResponse
    {
        $user = Auth::user();

        if ($mail->sender_id !== $user->id && $mail->recipient_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mail->update(['status' => 'deleted']);

        return response()->json([
            'success' => true,
            'message' => 'Mail moved to trash'
        ]);
    }

    public function delete(Mail $mail): JsonResponse
    {
        $user = Auth::user();

        if ($mail->sender_id !== $user->id && $mail->recipient_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mail->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mail deleted permanently'
        ]);
    }

    public function getUnreadCount(): JsonResponse
    {
        $user = Auth::user();

        $unreadCount = Mail::where('recipient_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'unread_count' => $unreadCount
        ]);
    }
}
