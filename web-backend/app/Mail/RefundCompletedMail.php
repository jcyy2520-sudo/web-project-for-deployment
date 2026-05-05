<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Refund;

class RefundCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $refund;
    public $appointmentDetails;
    public $userDetails;

    /**
     * Create a new message instance.
     */
    public function __construct(Refund $refund)
    {
        $this->refund = $refund;
        $this->appointmentDetails = [
            'date' => $refund->appointment?->appointment_date,
            'time' => $refund->appointment?->appointment_time,
            'service' => $refund->appointment?->service?->name ?? 'N/A',
            'payment_amount' => $refund->appointment?->payment_amount,
        ];
        $this->userDetails = [
            'name' => $refund->appointment?->user?->first_name . ' ' . $refund->appointment?->user?->last_name,
            'email' => $refund->appointment?->user?->email,
        ];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Refund Has Been Processed - ₱' . number_format($this->refund->refund_amount, 2),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.refund-completed',
            with: [
                'refund' => $this->refund,
                'appointmentDetails' => $this->appointmentDetails,
                'userDetails' => $this->userDetails,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
