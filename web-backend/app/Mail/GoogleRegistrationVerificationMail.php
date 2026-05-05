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
        $verificationLink = route('google.verify', ['verificationCode' => $this->verificationCode]);

        return new Content(
            markdown: 'emails.google-registration-verification',
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
