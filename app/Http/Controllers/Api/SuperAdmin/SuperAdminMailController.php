<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SuperAdminMailRequest;
use App\Http\Resources\SuperAdmin\SuperAdminMailResource;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SuperAdminMailController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            // Get mail templates and sent mails
            $templates = $this->getMailTemplates();
            $sentMails = $this->getSentMails($request);
            $businesses = $this->getBusinesses();
            
            return response()->json([
                'success' => true,
                'templates' => $templates,
                'sent_mails' => $sentMails,
                'businesses' => $businesses
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin mail index error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve mail data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendMail(SuperAdminMailRequest $request): JsonResponse
    {
        try {
            $recipients = $this->getRecipients($request->recipient_type, $request->business_id);
            
            // Send mail to each recipient
            foreach ($recipients as $recipient) {
                Mail::send('emails.super-admin-broadcast', [
                    'subject' => $request->subject,
                    'message' => $request->message,
                    'recipient' => $recipient
                ], function ($message) use ($request, $recipient) {
                    $message->to($recipient['email'], $recipient['name'])
                           ->subject($request->subject);
                });
            }
            
            // Save to database
            $this->saveMailToDatabase($request, count($recipients));
            
            return response()->json([
                'success' => true,
                'message' => 'Mail sent successfully to ' . count($recipients) . ' recipients'
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin mail sending error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send mail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getTemplates(): JsonResponse
    {
        try {
            $templates = $this->getMailTemplates();
            
            return response()->json([
                'success' => true,
                'templates' => $templates
            ]);
        } catch (\Exception $e) {
            Log::error('Get mail templates error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getHistory(Request $request): JsonResponse
    {
        try {
            $sentMails = $this->getSentMails($request);
            
            return response()->json([
                'success' => true,
                'sent_mails' => $sentMails
            ]);
        } catch (\Exception $e) {
            Log::error('Get mail history error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve mail history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function saveTemplate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'subject' => 'required|string|max:255',
                'content' => 'required|string'
            ]);

            // Save template to activity_logs table as a template
            DB::table('activity_logs')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'causer_type' => 'App\\Models\\Central\\SuperAdmin',
                'causer_id' => 'system',
                'action' => 'mail_template_created',
                'module' => 'mail',
                'description' => "Template: {$request->name}",
                'properties' => json_encode([
                    'name' => $request->name,
                    'subject' => $request->subject,
                    'content' => $request->content,
                    'usage_count' => 0
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template saved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Save template error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to save template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getMailTemplates(): array
    {
        try {
            // Get real templates from database
            $templates = DB::table('activity_logs')
                ->where('action', 'mail_template_created')
                ->where('module', 'mail')
                ->orderBy('created_at', 'desc')
                ->get();

            return $templates->map(function ($template) {
                $properties = json_decode($template->properties, true);
                return [
                    'id' => $template->id,
                    'name' => $properties['name'] ?? 'Unnamed Template',
                    'subject' => $properties['subject'] ?? 'No Subject',
                    'content' => $properties['content'] ?? '',
                    'usage' => $properties['usage_count'] ?? 0
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Error getting mail templates: ' . $e->getMessage());
            return [];
        }
    }

    private function getSentMails(Request $request): array
    {
        try {
            // Get real data from activity_logs table
            $mails = DB::table('activity_logs')
                ->where('action', 'mail_sent')
                ->where('module', 'mail')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return $mails->map(function ($mail) {
                $properties = json_decode($mail->properties, true);
                return [
                    'id' => $mail->id,
                    'subject' => $properties['subject'] ?? 'Unknown Subject',
                    'recipient_type' => $properties['recipient_type'] ?? 'unknown',
                    'sent_count' => $properties['recipient_count'] ?? 0,
                    'sent_at' => $mail->created_at,
                    'status' => 'sent'
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Error getting sent mails: ' . $e->getMessage());
            return [];
        }
    }

    private function getBusinesses(): array
    {
        try {
            return Tenant::select('id', 'data')
                ->get()
                ->map(function ($tenant) {
                    $data = $tenant->data ?? [];
                    return [
                        'id' => $tenant->id,
                        'name' => $data['name'] ?? 'Unknown Business',
                        'email' => $data['contact_email'] ?? null
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Get businesses error: ' . $e->getMessage());
            return [];
        }
    }

    private function getRecipients(string $type, ?string $businessId = null): array
    {
        $recipients = [];
        
        switch ($type) {
            case 'all_admins':
                // Get all super admins
                $admins = SuperAdmin::select('name', 'email')->get();
                foreach ($admins as $admin) {
                    $recipients[] = [
                        'name' => $admin->name,
                        'email' => $admin->email
                    ];
                }
                break;
                
            case 'specific_business':
                if ($businessId) {
                    $tenant = Tenant::find($businessId);
                    if ($tenant && isset($tenant->data['contact_email'])) {
                        $recipients[] = [
                            'name' => $tenant->data['name'] ?? 'Business Admin',
                            'email' => $tenant->data['contact_email']
                        ];
                    }
                }
                break;
                
            case 'all_members':
                // This would require querying all tenant databases
                // For now, return empty array
                break;
        }
        
        return $recipients;
    }

    private function saveMailToDatabase(SuperAdminMailRequest $request, int $recipientCount): void
    {
        try {
            // Save to activity_logs table
            DB::table('activity_logs')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'causer_type' => 'App\\Models\\Central\\SuperAdmin',
                'causer_id' => 'system',
                'action' => 'mail_sent',
                'module' => 'mail',
                'description' => "Email sent: {$request->subject}",
                'properties' => json_encode([
                    'subject' => $request->subject,
                    'recipient_type' => $request->recipient_type,
                    'recipient_count' => $recipientCount,
                    'business_id' => $request->business_id,
                    'message_preview' => substr($request->message, 0, 100)
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save mail to database: ' . $e->getMessage());
        }
    }

    private function logMailSending(SuperAdminMailRequest $request, int $recipientCount): void
    {
        // This would typically save to a database table
        Log::info('Mail sent', [
            'subject' => $request->subject,
            'recipient_type' => $request->recipient_type,
            'recipient_count' => $recipientCount,
            'sent_by' => 'system'
        ]);
    }
}
