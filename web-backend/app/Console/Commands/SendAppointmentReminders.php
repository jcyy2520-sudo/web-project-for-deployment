<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use App\Models\Notification;
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
    protected $description = 'Send email reminders to users at 2 hours, 1 hour, and 30 minutes before their appointment';

    /**
     * Reminder levels configuration.
     * Each level defines: minutes before appointment, required minimum level to be eligible,
     * the new level after sending, and a human-readable label.
     */
    private const REMINDER_LEVELS = [
        // Level 1: 2-hour reminder (sent when reminder_level < 1)
        [
            'minutes'       => 120,
            'max_level'     => 0,   // only send if no reminder sent yet
            'new_level'     => 1,
            'label'         => '2 hours',
            'buffer'        => 5,   // ± minute tolerance window
        ],
        // Level 2: 1-hour reminder (sent when reminder_level < 2)
        [
            'minutes'       => 60,
            'max_level'     => 1,   // only send if at most the 2hr reminder was sent
            'new_level'     => 2,
            'label'         => '1 hour',
            'buffer'        => 5,
        ],
        // Level 3: 30-minute reminder (sent when reminder_level < 3)
        [
            'minutes'       => 30,
            'max_level'     => 2,   // only send if at most the 1hr reminder was sent
            'new_level'     => 3,
            'label'         => '30 minutes',
            'buffer'        => 5,
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for upcoming appointments needing reminders...');

        $now = Carbon::now();
        $sentCount = 0;
        $failedCount = 0;

        // Fetch all eligible appointments once (approved/pending, today or tomorrow, not fully reminded)
        $appointments = Appointment::with(['user', 'staff', 'service'])
            ->whereIn('status', ['approved', 'pending'])
            ->where(function ($q) {
                $q->whereNull('reminder_level')->orWhere('reminder_level', '<', 3);
            })
            ->whereDate('appointment_date', '>=', $now->toDateString())
            ->whereDate('appointment_date', '<=', $now->copy()->addDay()->toDateString())
            ->get();

        if ($appointments->isEmpty()) {
            $this->info('No upcoming appointments found.');
            return Command::SUCCESS;
        }

        foreach ($appointments as $appointment) {
            // Parse the full appointment datetime
            $appointmentDateTime = Carbon::parse(
                $appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time
            );

            // Skip appointments that are already in the past
            if ($appointmentDateTime->isPast()) {
                continue;
            }

            $minutesUntil = $now->diffInMinutes($appointmentDateTime, false);
            $currentLevel = $appointment->reminder_level ?? 0;

            // Check each reminder level (from earliest to latest)
            foreach (self::REMINDER_LEVELS as $level) {
                // Skip if this level was already sent
                if ($currentLevel >= $level['new_level']) {
                    continue;
                }

                // Check if the appointment falls within this reminder's time window
                $windowMin = $level['minutes'] - $level['buffer'];
                $windowMax = $level['minutes'] + $level['buffer'];

                if ($minutesUntil >= $windowMin && $minutesUntil <= $windowMax) {
                    // This appointment needs this reminder level
                    try {
                        $this->sendReminder($appointment, $level);
                        $sentCount++;

                        $this->info("✓ [{$level['label']}] Reminder sent to {$appointment->user->email} for appointment #{$appointment->id}");
                    } catch (\Exception $e) {
                        $failedCount++;
                        $this->error("✗ Failed [{$level['label']}] reminder for appointment #{$appointment->id}: {$e->getMessage()}");
                        \Log::error("Failed to send appointment reminder", [
                            'appointment_id' => $appointment->id,
                            'user_email' => $appointment->user->email ?? 'N/A',
                            'level' => $level['label'],
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // Only send one reminder per appointment per run
                    break;
                }
            }
        }

        $this->info("─────────────────────────────────");
        $this->info("Summary:");
        $this->info("• Appointments checked: {$appointments->count()}");
        $this->info("• Reminders sent: {$sentCount}");
        if ($failedCount > 0) {
            $this->warn("• Failed: {$failedCount}");
        }
        $this->info("─────────────────────────────────");

        return Command::SUCCESS;
    }

    /**
     * Send the reminder email + in-app notification and update the appointment.
     */
    private function sendReminder(Appointment $appointment, array $level): void
    {
        $user = $appointment->user;

        // 1. Send email reminder
        Mail::to($user->email)->send(
            new AppointmentReminderMail($appointment, $level['label'])
        );

        // 2. Update appointment reminder tracking
        $appointment->update([
            'reminder_sent'    => true,
            'reminder_sent_at' => now(),
            'reminder_level'   => $level['new_level'],
        ]);

        // 3. Create in-app notification
        try {
            Notification::create([
                'user_id'      => $user->id,
                'type'         => 'appointment_reminder',
                'title'        => 'Appointment Reminder',
                'message'      => "Your appointment is in {$level['label']}! "
                    . ($appointment->service?->name ?? $appointment->service_type ?? 'Appointment')
                    . " on {$appointment->appointment_date->format('M d, Y')}"
                    . " at " . Carbon::parse($appointment->appointment_time)->format('g:i A'),
                'icon'         => '🔔',
                'color'        => $level['new_level'] === 3 ? 'red' : ($level['new_level'] === 2 ? 'orange' : 'blue'),
                'related_id'   => $appointment->id,
                'related_type' => 'appointment',
                'data'         => [
                    'appointment_id'   => $appointment->id,
                    'reminder_level'   => $level['new_level'],
                    'reminder_label'   => $level['label'],
                    'appointment_date' => $appointment->appointment_date->format('Y-m-d'),
                    'appointment_time' => $appointment->appointment_time,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::warning("In-app notification creation failed for appointment #{$appointment->id}: {$e->getMessage()}");
            // Don't fail the whole reminder if just the in-app notification fails
        }

        // 4. Log the action
        try {
            \App\Models\ActionLog::log(
                'send_appointment_reminder',
                "Sent {$level['label']} appointment reminder to {$user->first_name} {$user->last_name}"
                    . " for appointment on {$appointment->appointment_date->format('Y-m-d')}"
                    . " at {$appointment->appointment_time}",
                'Appointment',
                $appointment->id
            );
        } catch (\Exception $e) {
            // ActionLog is not critical
        }
    }
}
