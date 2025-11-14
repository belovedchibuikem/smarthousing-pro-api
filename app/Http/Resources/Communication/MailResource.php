<?php

namespace App\Http\Resources\Communication;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $senderName = $this->whenLoaded('sender') 
            ? $this->sender->first_name . ' ' . $this->sender->last_name 
            : null;
        $recipientName = $this->whenLoaded('recipient') 
            ? $this->recipient->first_name . ' ' . $this->recipient->last_name 
            : null;

        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'recipient_id' => $this->recipient_id,
            'subject' => $this->subject,
            'body' => $this->body,
            'type' => $this->type,
            'status' => $this->status,
            'category' => $this->category,
            'folder' => $this->folder,
            'is_starred' => $this->is_starred ?? false,
            'is_read' => $this->isRead(),
            'is_unread' => $this->isUnread(),
            'has_attachment' => $this->whenLoaded('attachments', function() {
                return $this->attachments->count() > 0;
            }, false),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'parent_id' => $this->parent_id,
            'cc' => $this->cc ?? [],
            'bcc' => $this->bcc ?? [],
            'sender' => $this->whenLoaded('sender', function () {
                return [
                    'id' => $this->sender->id,
                    'name' => $this->sender->first_name . ' ' . $this->sender->last_name,
                    'email' => $this->sender->email,
                ];
            }, [
                'id' => $this->sender_id,
                'name' => 'Unknown',
                'email' => '',
            ]),
            'recipient' => $this->whenLoaded('recipient', function () {
                return [
                    'id' => $this->recipient->id,
                    'name' => $this->recipient->first_name . ' ' . $this->recipient->last_name,
                    'email' => $this->recipient->email,
                ];
            }, $this->recipient_id ? [
                'id' => $this->recipient_id,
                'name' => 'Unknown',
                'email' => '',
            ] : null),
            'attachments' => $this->whenLoaded('attachments', function() {
                return $this->attachments->map(function($attachment) {
                    return [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'file_path' => $attachment->file_path,
                        'file_size' => $attachment->file_size,
                        'mime_type' => $attachment->mime_type,
                        'download_url' => Storage::disk('public')->url($attachment->file_path),
                    ];
                });
            }, []),
            'from' => $senderName,
            'fromEmail' => $this->whenLoaded('sender') ? $this->sender->email : null,
            'to' => $recipientName,
            'toEmail' => $this->whenLoaded('recipient') ? $this->recipient->email : null,
            'preview' => $this->body ? substr(strip_tags($this->body), 0, 100) . '...' : '',
            'date' => $this->sent_at?->toIso8601String() ?? $this->created_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
