<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use App\Mail\AppointmentReminderMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email reminders to users 1 hour before their appointment';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for upcoming appointments...');

        // Get current time
        $now = Carbon::now();
        
        // Calculate the time window: 60 minutes from now (with 5-minute buffer for flexibility)
        $reminderTime = $now->copy()->addMinutes(60);
        $reminderTimeMin = $reminderTime->copy()->subMinutes(2); // 58 minutes from now
        $reminderTimeMax = $reminderTime->copy()->addMinutes(2); // 62 minutes from now

        // Find appointments that:
        // 1. Are approved or pending
        // 2. Haven't had reminder sent yet
        // 3. Are scheduled within the next 58-62 minutes
        // 4. Are happening today or tomorrow
        $appointments = Appointment::with(['user', 'staff', 'service'])
            ->whereIn('status', ['approved', 'pending'])
            ->where('reminder_sent', false)
            ->whereDate('appointment_date', '>=', $now->toDateString())
            ->whereDate('appointment_date', '<=', $now->copy()->addDay()->toDateString())
            ->get()
            ->filter(function ($appointment) use ($now, $reminderTimeMin, $reminderTimeMax) {
                // Combine appointment date and time
                $appointmentDateTime = Carbon::parse(
                    $appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time
                );

                // Check if appointment is in the target window (58-62 minutes from now)
                return $appointmentDateTime->between($reminderTimeMin, $reminderTimeMax);
            });

        $sentCount = 0;
        $failedCount = 0;

        foreach ($appointments as $appointment) {
            try {
                // Send reminder email
                Mail::to($appointment->user->email)->send(new AppointmentReminderMail($appointment));

                // Mark reminder as sent
                $appointment->update([
                    'reminder_sent' => true,
                    'reminder_sent_at' => now()
                ]);

                $this->info("✓ Reminder sent to {$appointment->user->email} for appointment #{$appointment->id}");
                $sentCount++;

                // Log the action
                \App\Models\ActionLog::log(
                    'send_appointment_reminder',
                    "Sent appointment reminder to {$appointment->user->first_name} {$appointment->user->last_name} for appointment on {$appointment->appointment_date->format('Y-m-d')} at {$appointment->appointment_time}",
                    'Appointment',
                    $appointment->id
                );
            } catch (\Exception $e) {
                $this->error("✗ Failed to send reminder for appointment #{$appointment->id}: {$e->getMessage()}");
                \Log::error("Failed to send appointment reminder: {$e->getMessage()}", [
                    'appointment_id' => $appointment->id,
                    'user_email' => $appointment->user->email
                ]);
                $failedCount++;
            }
        }

        $this->info("─────────────────────────────────");
        $this->info("Summary:");
        $this->info("• Total reminders sent: {$sentCount}");
        if ($failedCount > 0) {
            $this->warn("• Failed: {$failedCount}");
        }
        $this->info("─────────────────────────────────");

        return Command::SUCCESS;
    }
}
