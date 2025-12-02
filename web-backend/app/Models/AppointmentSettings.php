<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentSettings extends Model
{
    protected $fillable = [
        'daily_booking_limit_per_user',
        'is_active',
        'description',
        'last_updated_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'daily_booking_limit_per_user' => 'integer',
    ];

    /**
     * Get the user who last updated these settings
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    /**
     * Get the current active settings (singleton pattern)
     */
    public static function getCurrent()
    {
        return self::where('is_active', true)->first() ?? self::create([
            'daily_booking_limit_per_user' => 3,
            'is_active' => true,
            'description' => 'Default appointment settings',
        ]);
    }

    /**
     * Check if a user has reached their daily booking limit
     */
    public static function userHasReachedDailyLimit($userId, $date = null)
    {
        $date = $date ?? now()->format('Y-m-d');
        $settings = self::getCurrent();

        if (!$settings || !$settings->is_active) {
            return false;
        }

        $todayBookings = Appointment::where('user_id', $userId)
            ->where('appointment_date', $date)
            ->where('status', '!=', 'cancelled')
            ->count();

        return $todayBookings >= $settings->daily_booking_limit_per_user;
    }

    /**
     * Get remaining bookings for user today
     */
    public static function getRemainingBookingsForUser($userId, $date = null)
    {
        $date = $date ?? now()->format('Y-m-d');
        $settings = self::getCurrent();

        if (!$settings || !$settings->is_active) {
            return null;
        }

        $todayBookings = Appointment::where('user_id', $userId)
            ->where('appointment_date', $date)
            ->where('status', '!=', 'cancelled')
            ->count();

        $remaining = $settings->daily_booking_limit_per_user - $todayBookings;

        return max(0, $remaining);
    }

    /**
     * Get all bookings for a user on a specific date
     */
    public static function getUserBookingsForDate($userId, $date = null)
    {
        $date = $date ?? now()->format('Y-m-d');

        return Appointment::where('user_id', $userId)
            ->where('appointment_date', $date)
            ->where('status', '!=', 'cancelled')
            ->select('id', 'appointment_date', 'appointment_time', 'status', 'service_id')
            ->with('service')
            ->get();
    }
}
