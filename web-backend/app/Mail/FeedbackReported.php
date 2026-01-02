<?php

namespace App\Mail;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedbackReported extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Feedback $feedback
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Feedback Has Been Reported - LegalEase'
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $reasonLabels = [
            'harassment' => 'Harassment',
            'hate_speech' => 'Hate Speech',
            'spam' => 'Spam',
            'threats' => 'Threats',
            'false_information' => 'False Information',
            'other' => 'Other'
        ];

        return new Content(
            view: 'emails.feedback-reported',
            with: [
                'feedback' => $this->feedback,
                'reason' => $reasonLabels[$this->feedback->reported_reason] ?? $this->feedback->reported_reason,
                'explanation' => $this->feedback->reported_explanation
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
