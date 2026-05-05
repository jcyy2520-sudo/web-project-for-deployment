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
    public $timeLabel;
    public $urgencyLevel;

    /**
     * @param Appointment $appointment
     * @param string      $timeLabel  Human-readable label like "2 hours", "1 hour", "30 minutes"
     */
    public function __construct(Appointment $appointment, string $timeLabel = '1 hour')
    {
        $this->appointment = $appointment;
        $this->timeLabel = $timeLabel;
        
        // Calculate exact time until appointment
        $appointmentDateTime = \Carbon\Carbon::parse(
            $appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time
        );
        $this->timeUntilAppointment = now()->diffInMinutes($appointmentDateTime);

        // Set urgency level for template styling
        if ($this->timeUntilAppointment <= 35) {
            $this->urgencyLevel = 'high';      // 30 min — red/urgent
        } elseif ($this->timeUntilAppointment <= 65) {
            $this->urgencyLevel = 'medium';    // 1 hour — orange
        } else {
            $this->urgencyLevel = 'low';       // 2 hours — blue/normal
        }
    }

    public function build()
    {
        $subjectPrefix = $this->urgencyLevel === 'high' ? '⚠️' : '🔔';

        return $this->subject("{$subjectPrefix} Appointment in {$this->timeLabel} - Legal Ease")
                    ->markdown('emails.appointment-reminder')
                    ->with([
                        'appointment' => $this->appointment,
                        'timeUntilAppointment' => $this->timeUntilAppointment,
                        'timeLabel' => $this->timeLabel,
                        'urgencyLevel' => $this->urgencyLevel,
                    ]);
    }
}
