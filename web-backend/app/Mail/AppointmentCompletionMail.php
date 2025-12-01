<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentCompletionMail extends Mailable
{
    use Queueable, SerializesModels;

    public $appointment;
    public $completedBy;

    public function __construct(Appointment $appointment, User $completedBy)
    {
        $this->appointment = $appointment;
        $this->completedBy = $completedBy;
    }

    public function build()
    {
        return $this->subject('Appointment Completion Confirmation - Legal Ease')
                    ->view('emails.appointment-completion')
                    ->with([
                        'appointment' => $this->appointment,
                        'completedBy' => $this->completedBy
                    ]);
    }
}
