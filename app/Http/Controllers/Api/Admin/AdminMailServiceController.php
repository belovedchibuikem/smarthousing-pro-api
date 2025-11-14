<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Mail;
use App\Models\Tenant\MailRecipient;
use App\Models\Tenant\MailAttachment;
use App\Models\Tenant\User;
use App\Models\Tenant\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class AdminMailServiceController extends Controller
{
    /**
     * Get mail service statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $totalMessages = Mail::count();
            $sentToday = Mail::where('status', 'sent')
                ->where('folder', 'sent')
                ->whereDate('sent_at', today())
                ->count();
            $activeUsers = User::where('status', 'active')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_messages' => $totalMessages,
                    'sent_today' => $sentToday,
                    'active_users' => $activeUsers,
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Mail service stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get inbox messages
     */
    public function inbox(Request $request): JsonResponse
    {
        
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            
            $query = Mail::with(['sender:id,first_name,last_name,email'])
                ->where(function($q) use ($user) {
                    $q->where('recipient_id', $user->id)
                      ->orWhereHas('recipients', function($subQ) use ($user) {
                          $subQ->where('recipient_id', $user->id);
                      });
                })
                ->where('folder', 'inbox')
                ->where('status', '!=', 'deleted');

            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                      ->orWhere('body', 'like', "%{$search}%");
                });
            }

            // Category filter
            if ($request->has('category') && $request->category !== 'all') {
                $query->where('category', $request->category);
            }

            // Read status filter
            if ($request->has('unread_only') && $request->unread_only) {
                $query->where(function($q) {
                    $q->where('is_read', false)->orWhereNull('read_at');
                });
            }

            $messages = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            // Format messages efficiently
            $formattedMessages = [];
            foreach ($messages->items() as $message) {
                $formattedMessages[] = $this->formatMessageSimple($message);
            }

            return response()->json([
                'success' => true,
                'data' => $formattedMessages,
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Inbox error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve inbox messages',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get draft messages
     */
    public function drafts(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $query = Mail::with(['sender:id,first_name,last_name,email'])
                ->where('sender_id', $user->id)
                ->where('folder', 'drafts')
                ->where('status', 'draft');

            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                      ->orWhere('body', 'like', "%{$search}%");
                });
            }

            $drafts = $query->orderBy('updated_at', 'desc')
                ->paginate($request->get('per_page', 15));

            $formattedDrafts = [];
            foreach ($drafts->items() as $draft) {
                $formattedDrafts[] = $this->formatMessageSimple($draft);
            }

            return response()->json([
                'success' => true,
                'data' => $formattedDrafts,
                'pagination' => [
                    'current_page' => $drafts->currentPage(),
                    'last_page' => $drafts->lastPage(),
                    'per_page' => $drafts->perPage(),
                    'total' => $drafts->total(),
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Drafts error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve drafts',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get sent messages
     */
    public function sent(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $query = Mail::with(['sender:id,first_name,last_name,email'])
                ->where('sender_id', $user->id)
                ->where('folder', 'sent')
                ->where('status', 'sent');

            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                      ->orWhere('body', 'like', "%{$search}%");
                });
            }

            // Category filter
            if ($request->has('category') && $request->category !== 'all') {
                $query->where('category', $request->category);
            }

            $messages = $query->orderBy('sent_at', 'desc')
                ->paginate($request->get('per_page', 15));

            $formattedMessages = [];
            foreach ($messages->items() as $message) {
                $formattedMessages[] = $this->formatMessageSimple($message);
            }

            return response()->json([
                'success' => true,
                'data' => $formattedMessages,
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Sent messages error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sent messages',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get outbox messages (pending delivery)
     */
    public function outbox(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $query = Mail::with(['sender:id,first_name,last_name,email'])
                ->where('sender_id', $user->id)
                ->whereIn('status', ['sent', 'pending']);

            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                      ->orWhere('body', 'like', "%{$search}%");
                });
            }

            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            $messages = $query->orderBy('sent_at', 'desc')
                ->paginate($request->get('per_page', 15));

            $formattedMessages = [];
            foreach ($messages->items() as $message) {
                $formattedMessages[] = $this->formatMessageSimple($message);
            }

            return response()->json([
                'success' => true,
                'data' => $formattedMessages,
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Outbox error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve outbox messages',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Compose and send or save draft
     */
    public function compose(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $validator = Validator::make($request->all(), [
                'subject' => 'nullable|string|max:255',
                'message' => 'nullable|string',
                'category' => 'nullable|string|in:general,investment,contribution,loan,property',
                'recipients' => 'required|string|in:all,active,specific',
                'recipient_ids' => 'required_if:recipients,specific|array|min:1',
                'recipient_ids.*' => 'required|uuid|exists:users,id',
                'cc' => 'nullable|string',
                'bcc' => 'nullable|string',
                'is_urgent' => 'nullable|boolean',
                'save_as_draft' => 'nullable|boolean',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:10240', // 10MB max per file
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $recipientIds = $this->getRecipientIds($request->recipients, $request->recipient_ids ?? []);

            $mail = Mail::create([
                'sender_id' => $user->id,
                'recipient_id' => $recipientIds[0] ?? null,
                'subject' => $request->subject ?? '',
                'body' => $request->message ?? '',
                'category' => $request->category ?? 'general',
                'recipient_type' => $request->recipients,
                'cc' => $request->cc ? explode(',', $request->cc) : null,
                'bcc' => $request->bcc ? explode(',', $request->bcc) : null,
                'is_urgent' => $request->is_urgent ?? false,
                'status' => $request->save_as_draft ? 'draft' : 'sent',
                'folder' => $request->save_as_draft ? 'drafts' : 'sent',
                'sent_at' => $request->save_as_draft ? null : now(),
                'is_read' => false,
            ]);

            // Create recipients
            foreach ($recipientIds as $recipientId) {
                MailRecipient::create([
                    'mail_id' => $mail->id,
                    'recipient_id' => $recipientId,
                    'type' => 'to',
                    'status' => $request->save_as_draft ? 'pending' : 'delivered',
                ]);
            }

            // Handle CC
            if ($request->cc) {
                $ccEmails = array_map('trim', explode(',', $request->cc));
                foreach ($ccEmails as $email) {
                    $ccUser = User::where('email', $email)->first();
                    if ($ccUser) {
                        MailRecipient::create([
                            'mail_id' => $mail->id,
                            'recipient_id' => $ccUser->id,
                            'type' => 'cc',
                            'status' => $request->save_as_draft ? 'pending' : 'delivered',
                        ]);
                    }
                }
            }

            // Handle attachments
            if ($request->hasFile('attachments')) {
                $files = $request->file('attachments');
                // Handle both single file and array of files
                if (!is_array($files)) {
                    $files = [$files];
                }
                foreach ($files as $index => $file) {
                    if ($file && $file->isValid()) {
                        $path = $file->store('mail-attachments', 'public');
                        MailAttachment::create([
                            'mail_id' => $mail->id,
                            'name' => $file->getClientOriginalName(),
                            'file_path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'file_size' => $file->getSize(),
                            'order' => $index,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $request->save_as_draft ? 'Draft saved successfully' : 'Message sent successfully',
                'data' => $this->formatMessage($mail->load(['sender', 'recipients.recipient', 'attachments']))
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Compose error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to ' . ($request->save_as_draft ? 'save draft' : 'send message'),
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get single message
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $mail = Mail::with(['sender', 'recipients.recipient', 'attachments', 'replies'])
                ->findOrFail($id);

            // Check authorization
            if ($mail->sender_id !== $user->id && 
                $mail->recipient_id !== $user->id &&
                !$mail->recipients()->where('recipient_id', $user->id)->exists()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Mark as read if user is recipient
            if (($mail->recipient_id === $user->id || 
                 $mail->recipients()->where('recipient_id', $user->id)->exists()) && 
                !$mail->is_read) {
                $mail->update(['is_read' => true, 'read_at' => now()]);
                
                // Update recipient read status
                $mail->recipients()->where('recipient_id', $user->id)
                    ->update(['status' => 'read', 'read_at' => now()]);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatMessage($mail, true)
            ]);
        } catch (Exception $e) {
            Log::error('Show message error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve message',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Update message (star, archive, etc.)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $mail = Mail::findOrFail($id);

            // Check authorization
            if ($mail->sender_id !== $user->id && 
                $mail->recipient_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'is_starred' => 'nullable|boolean',
                'is_archived' => 'nullable|boolean',
                'is_read' => 'nullable|boolean',
                'subject' => 'nullable|string|max:255',
                'message' => 'nullable|string',
                'category' => 'nullable|string|in:general,investment,contribution,loan,property',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [];
            if ($request->has('is_starred')) $updateData['is_starred'] = $request->is_starred;
            if ($request->has('is_archived')) $updateData['is_archived'] = $request->is_archived;
            if ($request->has('is_read')) {
                $updateData['is_read'] = $request->is_read;
                $updateData['read_at'] = $request->is_read ? now() : null;
            }
            if ($request->has('subject')) $updateData['subject'] = $request->subject;
            if ($request->has('message')) $updateData['body'] = $request->message;
            if ($request->has('category')) $updateData['category'] = $request->category;

            $mail->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Message updated successfully',
                'data' => $this->formatMessage($mail->load(['sender', 'recipients.recipient', 'attachments']))
            ]);
        } catch (Exception $e) {
            Log::error('Update message error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update message',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Bulk operations
     */
    public function bulk(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|string|in:mark_read,mark_unread,delete,archive,star,unstar',
                'message_ids' => 'required|array',
                'message_ids.*' => 'uuid|exists:mails,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $messages = Mail::whereIn('id', $request->message_ids)
                ->where(function($q) use ($user) {
                    $q->where('sender_id', $user->id)
                      ->orWhere('recipient_id', $user->id);
                })
                ->get();

            DB::beginTransaction();

            foreach ($messages as $message) {
                switch ($request->action) {
                    case 'mark_read':
                        $message->update(['is_read' => true, 'read_at' => now()]);
                        break;
                    case 'mark_unread':
                        $message->update(['is_read' => false, 'read_at' => null]);
                        break;
                    case 'delete':
                        $message->update(['folder' => 'trash', 'status' => 'deleted']);
                        break;
                    case 'archive':
                        $message->update(['is_archived' => true]);
                        break;
                    case 'star':
                        $message->update(['is_starred' => true]);
                        break;
                    case 'unstar':
                        $message->update(['is_starred' => false]);
                        break;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => ucfirst(str_replace('_', ' ', $request->action)) . ' completed successfully',
                'affected_count' => $messages->count()
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Bulk operation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform bulk operation',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Delete message
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $mail = Mail::findOrFail($id);

            // Check authorization
            if ($mail->sender_id !== $user->id && 
                $mail->recipient_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $mail->update(['folder' => 'trash', 'status' => 'deleted']);

            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Delete message error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete message',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Helper: Get recipient IDs based on recipient type
     */
    private function getRecipientIds(string $recipientType, array $specificIds = []): array
    {
        switch ($recipientType) {
            case 'all':
                return User::where('status', 'active')
                    ->pluck('id')
                    ->toArray();
            case 'active':
                return User::where('status', 'active')
                    ->pluck('id')
                    ->toArray();
            case 'specific':
                return $specificIds;
            default:
                return [];
        }
    }

    /**
     * Helper: Format message for response (simple version for list views)
     */
    private function formatMessageSimple(Mail $message): array
    {
        $senderName = $message->sender ? $message->sender->first_name . ' ' . $message->sender->last_name : 'Unknown';
        $recipientCount = MailRecipient::where('mail_id', $message->id)->count();
        
        return [
            'id' => $message->id,
            'from' => $senderName,
            'from_email' => $message->sender->email ?? null,
            'to' => $recipientCount > 0 ? $recipientCount . ' recipient(s)' : 'No recipient',
            'subject' => $message->subject ?? '',
            'preview' => substr(strip_tags($message->body ?? ''), 0, 100),
            'date' => $message->sent_at ? $message->sent_at->format('Y-m-d') : $message->created_at->format('Y-m-d'),
            'time' => $message->sent_at ? $message->sent_at->format('h:i A') : $message->created_at->format('h:i A'),
            'category' => $message->category ?? 'general',
            'is_read' => $message->is_read ?? false,
            'is_starred' => $message->is_starred ?? false,
            'is_archived' => $message->is_archived ?? false,
            'is_urgent' => $message->is_urgent ?? false,
            'status' => $message->status ?? 'sent',
            'recipients_count' => $recipientCount,
            'has_attachment' => MailAttachment::where('mail_id', $message->id)->exists(),
        ];
    }

    /**
     * Helper: Format message for response (full version for detail view)
     */
    private function formatMessage(Mail $message, bool $full = false): array
    {
        // Load relationships only when needed
        if ($full) {
            $message->load(['sender:id,first_name,last_name,email', 'recipients.recipient:id,first_name,last_name,email', 'attachments']);
        } else {
            $message->load(['sender:id,first_name,last_name,email']);
        }
        
        $recipients = $full ? $message->recipients : collect([]);
        $recipientCount = $full ? $recipients->count() : MailRecipient::where('mail_id', $message->id)->count();
        $primaryRecipient = $message->recipient;

        $formatted = [
            'id' => $message->id,
            'from' => $message->sender ? $message->sender->first_name . ' ' . $message->sender->last_name : 'Unknown',
            'from_email' => $message->sender->email ?? null,
            'to' => $primaryRecipient ? $primaryRecipient->first_name . ' ' . $primaryRecipient->last_name : ($recipientCount > 0 ? $recipientCount . ' recipients' : 'No recipient'),
            'subject' => $message->subject ?? '',
            'preview' => $full ? $message->body : substr(strip_tags($message->body ?? ''), 0, 100),
            'date' => $message->sent_at ? $message->sent_at->format('Y-m-d') : $message->created_at->format('Y-m-d'),
            'time' => $message->sent_at ? $message->sent_at->format('h:i A') : $message->created_at->format('h:i A'),
            'category' => $message->category ?? 'general',
            'is_read' => $message->is_read ?? false,
            'is_starred' => $message->is_starred ?? false,
            'is_archived' => $message->is_archived ?? false,
            'is_urgent' => $message->is_urgent ?? false,
            'status' => $message->status ?? 'sent',
            'recipients_count' => $recipientCount,
            'has_attachment' => $full ? $message->attachments->count() > 0 : MailAttachment::where('mail_id', $message->id)->exists(),
        ];

        if ($full) {
            $formatted['content'] = $message->body ?? '';
            $formatted['attachments'] = $message->attachments->map(function($attachment) {
                return [
                    'id' => $attachment->id,
                    'name' => $attachment->name,
                    'url' => $attachment->url,
                    'size' => $attachment->file_size,
                    'mime_type' => $attachment->mime_type,
                ];
            });
            $formatted['recipients'] = $recipients->map(function($recipient) {
                return [
                    'id' => $recipient->id,
                    'type' => $recipient->type,
                    'name' => $recipient->recipient ? $recipient->recipient->first_name . ' ' . $recipient->recipient->last_name : ($recipient->name ?? ''),
                    'email' => $recipient->recipient ? $recipient->recipient->email : ($recipient->email ?? ''),
                ];
            });
            $formatted['cc'] = $message->cc;
            $formatted['bcc'] = $message->bcc;
        }

        return $formatted;
    }
}

