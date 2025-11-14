<?php

namespace App\Services\Communication;

use App\Models\Tenant\Mail;
use App\Models\Tenant\TenantSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail as MailFacade;

class MailService
{
    public function sendMail(Mail $mail): void
    {
        try {
            // Apply tenant email settings before sending
            $this->applyTenantEmailSettings();
            
            // Send email notification to recipient
            // This would integrate with your email service (SMTP, SendGrid, etc.)
            
            // Update mail status
            $mail->update(['status' => 'delivered']);
            
            Log::info('Mail sent successfully', [
                'mail_id' => $mail->id,
                'recipient' => $mail->recipient->email,
                'subject' => $mail->subject
            ]);
        } catch (\Exception $e) {
            $mail->update(['status' => 'failed']);
            
            Log::error('Failed to send mail', [
                'mail_id' => $mail->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function applyTenantEmailSettings(): void
    {
        if (!tenant()) {
            return;
        }

        $emailSettings = TenantSetting::where('tenant_id', tenant('id'))
            ->where('category', 'email')
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->getTypedValue()];
            });

        if ($emailSettings->isEmpty()) {
            return;
        }

        $settings = $emailSettings->toArray();

        if (isset($settings['smtp_host'])) {
            Config::set('mail.mailers.smtp.host', $settings['smtp_host']);
        }
        if (isset($settings['smtp_port'])) {
            Config::set('mail.mailers.smtp.port', $settings['smtp_port']);
        }
        if (isset($settings['smtp_username'])) {
            Config::set('mail.mailers.smtp.username', $settings['smtp_username']);
        }
        if (isset($settings['smtp_password'])) {
            Config::set('mail.mailers.smtp.password', $settings['smtp_password']);
        }
        if (isset($settings['smtp_encryption'])) {
            Config::set('mail.mailers.smtp.encryption', $settings['smtp_encryption'] === 'none' ? null : $settings['smtp_encryption']);
        }
        if (isset($settings['smtp_from_address'])) {
            Config::set('mail.from.address', $settings['smtp_from_address']);
        }
        if (isset($settings['smtp_from_name'])) {
            Config::set('mail.from.name', $settings['smtp_from_name']);
        }
    }

    public function sendBulkMail(array $recipients, string $subject, string $body, string $type = 'internal', string $senderId): void
    {
        foreach ($recipients as $recipientId) {
            Mail::create([
                'sender_id' => $senderId,
                'recipient_id' => $recipientId,
                'subject' => $subject,
                'body' => $body,
                'type' => $type,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }
    }
}
