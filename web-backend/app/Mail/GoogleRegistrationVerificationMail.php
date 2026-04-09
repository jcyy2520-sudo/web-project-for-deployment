<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

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
        // Generate signed URLs for confirmation and rejection
        $confirmationLink = URL::signedRoute('registration.confirm', ['token' => $this->verificationCode]);
        $rejectionLink = URL::signedRoute('registration.reject', ['token' => $this->verificationCode]);

        return new Content(
            view: 'emails.google-registration-verification',
            with: [
                'user' => $this->user,
                'confirmationLink' => $confirmationLink,
                'rejectionLink' => $rejectionLink,
                'verificationCode' => $this->verificationCode,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
