<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $appointment;
    public $timeUntilAppointment;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
        
        // Calculate time until appointment
        $appointmentDateTime = \Carbon\Carbon::parse(
            $appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time
        );
        $this->timeUntilAppointment = now()->diffInMinutes($appointmentDateTime);
    }

    public function build()
    {
        return $this->subject('🔔 Appointment Reminder - Legal Ease')
                    ->view('emails.appointment-reminder')
                    ->with([
                        'appointment' => $this->appointment,
                        'timeUntilAppointment' => $this->timeUntilAppointment
                    ]);
    }
}
