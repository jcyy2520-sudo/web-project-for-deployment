<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GoogleRegistrationVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $verificationCode
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Google Registration - ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        $verificationLink = config('app.frontend_url') . '/auth/verify-google?code=' . $this->verificationCode;

        return new Content(
            view: 'emails.google-registration-verification',
            with: [
                'user' => $this->user,
                'verificationLink' => $verificationLink,
                'verificationCode' => $this->verificationCode,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
