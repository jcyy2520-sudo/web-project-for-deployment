<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $receipt;

    public function __construct(array $receipt)
    {
        $this->receipt = $receipt;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Official Receipt {$this->receipt['receipt_id']} - Legal Ease",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.receipt',
            with: ['receipt' => $this->receipt],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
