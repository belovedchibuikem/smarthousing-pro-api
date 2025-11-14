<?php

namespace App\Http\Controllers\Api\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\SendMailRequest;
use App\Http\Resources\Communication\MailResource;
use App\Models\Tenant\Mail;
use App\Models\Tenant\MailAttachment;
use App\Models\Tenant\User;
use App\Services\Communication\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MailController extends Controller
{
    public function __construct(
        protected MailService $mailService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Mail::with(['sender', 'recipient']);

        // Filter by user if not admin
        if (!$request->user()->isAdmin()) {
            $query->where(function($q) use ($request) {
                $q->where('sender_id', $request->user()->id)
                  ->orWhere('recipient_id', $request->user()->id);
            });
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $mails = $query->paginate($request->get('per_page', 15));

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

    public function store(SendMailRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $isDraft = $request->save_as_draft || $request->status === 'draft';
            
            $mailData = [
                'sender_id' => $request->user()->id,
                'subject' => $request->subject ?? '',
                'body' => $request->body ?? '',
                'type' => $request->type ?? 'internal',
                'status' => $isDraft ? 'draft' : 'sent',
                'category' => $request->category,
            ];

            if (!$isDraft && $request->recipient_id) {
                $mailData['recipient_id'] = $request->recipient_id;
                $mailData['sent_at'] = now();
            } elseif ($request->recipient_id) {
                $mailData['recipient_id'] = $request->recipient_id;
            }

            if ($request->has('cc') && is_array($request->cc)) {
                $mailData['cc'] = $request->cc;
            }

            if ($request->has('bcc') && is_array($request->bcc)) {
                $mailData['bcc'] = $request->bcc;
            }

            $mail = Mail::create($mailData);

            // Handle attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $index => $file) {
                    $path = $file->store('mail-attachments', 'public');
                    MailAttachment::create([
                        'mail_id' => $mail->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'order' => $index,
                    ]);
                }
            }

            // Send email notification if not draft
            if (!$isDraft) {
                $this->mailService->sendMail($mail);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $isDraft ? 'Draft saved successfully' : 'Mail sent successfully',
                'mail' => new MailResource($mail->load(['sender', 'recipient', 'attachments']))
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to send mail: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, string $mailId): JsonResponse
    {
        $mail = Mail::find($mailId);

        if (!$request->user()->isAdmin() && $mail->sender_id !== $request->user()->id && $mail->recipient_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Mark as read if recipient
        if ($mail->recipient_id === $request->user()->id && !$mail->read_at) {
            $mail->update(['read_at' => now()]);
        }

        $mail->load(['sender', 'recipient']);

        return response()->json([
            'mail' => new MailResource($mail)
        ]);
    }

    public function markAsRead(Request $request, string $mailId): JsonResponse
    {
        $mail = Mail::find($mailId);
        if ($mail->recipient_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mail->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Mail marked as read'
        ]);
    }

    public function markAsUnread(Request $request, string $mailId): JsonResponse
    {
        $mail = Mail::find($mailId);
        if ($mail->recipient_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mail->update(['read_at' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Mail marked as unread'
        ]);
    }

    public function reply(Request $request, Mail $mail): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $reply = Mail::create([
            'sender_id' => $request->user()->id,
            'recipient_id' => $mail->sender_id,
            'subject' => 'Re: ' . $mail->subject,
            'body' => $request->body,
            'type' => 'internal',
            'status' => 'sent',
            'sent_at' => now(),
            'parent_id' => $mail->id,
        ]);

        // Send email notification
        $this->mailService->sendMail($reply);

        return response()->json([
            'success' => true,
            'message' => 'Reply sent successfully',
            'mail' => new MailResource($reply->load(['sender', 'recipient']))
        ], 201);
    }

    public function destroy(Request $request, string $mailId): JsonResponse
    {
        // Check if user can delete this mail
        $mail = Mail::find($mailId);
        if (!$request->user()->isAdmin() && $mail->sender_id !== $request->user()->id && $mail->recipient_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mail->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mail deleted successfully'
        ]);
    }

    /**
     * Get inbox messages (received messages)
     */
    public function inbox(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $query = Mail::with(['sender', 'recipient', 'attachments'])
            ->where('recipient_id', $userId)
            ->where('status', '!=', 'draft')
            ->where(function($q) {
                $q->whereNull('folder')
                  ->orWhere('folder', '!=', 'trash');
            });

        // Filter by read status
        if ($request->has('filter')) {
            if ($request->filter === 'unread') {
                $query->where(function($q) {
                    $q->where('is_read', false)
                      ->orWhereNull('read_at');
                });
            } elseif ($request->filter === 'read') {
                $query->where('is_read', true)
                      ->whereNotNull('read_at');
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%")
                  ->orWhereHas('sender', function($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by starred
        if ($request->has('starred') && $request->starred) {
            $query->where('is_starred', true);
        }

        $mails = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'messages' => MailResource::collection($mails),
            'pagination' => [
                'current_page' => $mails->currentPage(),
                'last_page' => $mails->lastPage(),
                'per_page' => $mails->perPage(),
                'total' => $mails->total(),
            ]
        ]);
    }

    /**
     * Get sent messages
     */
    public function sent(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $query = Mail::with(['sender', 'recipient', 'attachments'])
            ->where('sender_id', $userId)
            ->where('status', 'sent')
            ->where(function($q) {
                $q->whereNull('folder')
                  ->orWhere('folder', '!=', 'trash');
            });

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%")
                  ->orWhereHas('recipient', function($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $mails = $query->orderBy('sent_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'messages' => MailResource::collection($mails),
            'pagination' => [
                'current_page' => $mails->currentPage(),
                'last_page' => $mails->lastPage(),
                'per_page' => $mails->perPage(),
                'total' => $mails->total(),
            ]
        ]);
    }

    /**
     * Get outbox messages (sent but not yet delivered)
     */
    public function outbox(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $query = Mail::with(['sender', 'recipient', 'attachments'])
            ->where('sender_id', $userId)
            ->whereIn('status', ['sent', 'delivered'])
            ->where(function($q) {
                $q->whereNull('folder')
                  ->orWhere('folder', '!=', 'trash');
            });

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%")
                  ->orWhereHas('recipient', function($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $mails = $query->orderBy('sent_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'messages' => MailResource::collection($mails),
            'pagination' => [
                'current_page' => $mails->currentPage(),
                'last_page' => $mails->lastPage(),
                'per_page' => $mails->perPage(),
                'total' => $mails->total(),
            ]
        ]);
    }

    /**
     * Get draft messages
     */
    public function drafts(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $query = Mail::with(['sender', 'recipient', 'attachments'])
            ->where('sender_id', $userId)
            ->where('status', 'draft');

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%")
                  ->orWhereHas('recipient', function($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $mails = $query->orderBy('updated_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'messages' => MailResource::collection($mails),
            'pagination' => [
                'current_page' => $mails->currentPage(),
                'last_page' => $mails->lastPage(),
                'per_page' => $mails->perPage(),
                'total' => $mails->total(),
            ]
        ]);
    }

    /**
     * Toggle star status
     */
    public function toggleStar(Request $request, string $mailId): JsonResponse
    {
        // Check authorization
        $mail = Mail::find($mailId);
        if (!$request->user()->isAdmin() && $mail->sender_id !== $request->user()->id && $mail->recipient_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mail->update(['is_starred' => !$mail->is_starred]);

        return response()->json([
            'success' => true,
            'message' => $mail->is_starred ? 'Mail starred' : 'Mail unstarred',
            'is_starred' => $mail->is_starred
        ]);
    }

    /**
     * Bulk delete messages
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|uuid|exists:mails,id',
        ]);

        $userId = $request->user()->id;
        $mails = Mail::whereIn('id', $request->ids)
            ->where(function($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('recipient_id', $userId);
            })
            ->get();

        foreach ($mails as $mail) {
            // Only sender can delete sent messages, recipient can delete received messages
            if (($mail->sender_id === $userId && $mail->status === 'sent') || 
                ($mail->recipient_id === $userId)) {
                $mail->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Messages deleted successfully'
        ]);
    }

    /**
     * Bulk mark as read
     */
    public function bulkMarkAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|uuid|exists:mails,id',
        ]);

        $userId = $request->user()->id;
        Mail::whereIn('id', $request->ids)
            ->where('recipient_id', $userId)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read'
        ]);
    }

    /**
     * Get available recipients (admins/users that members can send mail to)
     */
    public function getRecipients(Request $request): JsonResponse
    {
        // For members, return only admin users
        // For admins, return all users
        $query = User::query();

        if (!$request->user()->isAdmin()) {
            // Members can only see admins
            $query->where(function($q) {
                $q->where('role', 'admin')
                  ->orWhere('role', 'super_admin')
                  ->orWhereHas('roles', function($roleQuery) {
                      $roleQuery->whereIn('name', ['admin', 'super_admin']);
                  });
            });
        }

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->select('id', 'first_name', 'last_name', 'email', 'role')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'users' => $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => trim($user->first_name . ' ' . $user->last_name) ?: $user->email,
                    'email' => $user->email,
                    'role' => $user->role,
                ];
            })
        ]);
    }
}
