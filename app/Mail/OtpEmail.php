<?php

namespace App\Mail;

use App\Models\Tenant\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $otp,
        public string $type = 'registration'
    ) {}

    public function envelope(): Envelope
    {
        $subject = match($this->type) {
            'registration' => 'Verify Your Email - Registration OTP',
            'password_reset' => 'Password Reset OTP',
            'email_verification' => 'Email Verification OTP',
            default => 'Your Verification Code',
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'user' => $this->user,
                'otp' => $this->otp,
                'type' => $this->type,
            ],
        );
    }
}

