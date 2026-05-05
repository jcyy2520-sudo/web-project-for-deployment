<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email_notifications',
        'email_appointment_approved',
        'email_appointment_declined',
        'email_appointment_reminder',
        'email_new_message',
        'email_status_change',
        'in_app_notifications',
        'in_app_appointment_updates',
        'in_app_messages',
        'in_app_reminders',
        'push_notifications',
        'push_appointment_updates',
        'quiet_hours'
    ];

    protected $casts = [
        'email_notifications' => 'boolean',
        'email_appointment_approved' => 'boolean',
        'email_appointment_declined' => 'boolean',
        'email_appointment_reminder' => 'boolean',
        'email_new_message' => 'boolean',
        'email_status_change' => 'boolean',
        'in_app_notifications' => 'boolean',
        'in_app_appointment_updates' => 'boolean',
        'in_app_messages' => 'boolean',
        'in_app_reminders' => 'boolean',
        'push_notifications' => 'boolean',
        'push_appointment_updates' => 'boolean',
        'quiet_hours' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isInQuietHours()
    {
        if (!$this->quiet_hours || !$this->quiet_hours['enabled'] ?? false) {
            return false;
        }

        $now = now();
        $start = $now->copy()->setTimeFromTimeString($this->quiet_hours['start']);
        $end = $now->copy()->setTimeFromTimeString($this->quiet_hours['end']);

        if ($start > $end) {
            return $now >= $start || $now < $end;
        }

        return $now >= $start && $now < $end;
    }
}
