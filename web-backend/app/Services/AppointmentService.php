<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BlackoutDate;
use Illuminate\Support\Collection;
use Exception;

/**
 * AppointmentService: Handles all appointment-related business logic
 * Responsibilities: Appointment management, status updates, scheduling
 */
class AppointmentService
{
    /**
     * Create a new appointment
     */
    public function createAppointment(array $data): Appointment
    {
        try {
            $appointment = Appointment::create([
                'user_id' => $data['user_id'],
                'service_id' => $data['service_id'] ?? null,
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $data['appointment_time'],
                'notes' => $data['notes'] ?? null,
                'type' => $data['type'] ?? 'consultation',
            ]);
            // New appointments must always start in 'pending' status
            $appointment->status = 'pending';
            $appointment->save();

            return $appointment;
        } catch (Exception $e) {
            throw new Exception('Failed to create appointment: ' . $e->getMessage());
        }
    }

    /**
     * Update appointment status
     */
    public function updateAppointmentStatus(Appointment $appointment, string $status, string $reason = null): Appointment
    {
        try {
            $appointment->decline_reason = $reason;
            // Set protected field explicitly (not mass-assignable)
            $appointment->status = $status;
            $appointment->save();

            return $appointment;
        } catch (Exception $e) {
            throw new Exception('Failed to update appointment status: ' . $e->getMessage());
        }
    }

    /**
     * Get appointments by status
     */
    public function getAppointmentsByStatus(string $status): Collection
    {
        try {
            return Appointment::where('status', $status)->get();
        } catch (Exception $e) {
            throw new Exception('Failed to fetch appointments: ' . $e->getMessage());
        }
    }

    /**
     * Get appointments by date range
     */
    public function getAppointmentsByDateRange(string $startDate, string $endDate): Collection
    {
        try {
            return Appointment::whereBetween('appointment_date', [$startDate, $endDate])->get();
        } catch (Exception $e) {
            throw new Exception('Failed to fetch appointments: ' . $e->getMessage());
        }
    }

    /**
     * Check if date is available
     */
    public function isDateAvailable(string $date): bool
    {
        try {
            return !BlackoutDate::where('date', $date)
                ->where(function ($q) {
                    $q->whereNull('is_recurring')->orWhere('is_recurring', false);
                })
                ->whereNull('start_time')
                ->whereNull('end_time')
                ->exists();
        } catch (Exception $e) {
            throw new Exception('Failed to check date availability: ' . $e->getMessage());
        }
    }

    /**
     * Add unavailable date (writes to blackout_dates)
     */
    public function addUnavailableDate(array $data): BlackoutDate
    {
        try {
            $allDay = $data['all_day'] ?? true;

            $blackoutDate = BlackoutDate::create([
                'date' => $data['date'],
                'reason' => $data['reason'] ?? null,
                'start_time' => $allDay ? null : ($data['start_time'] ?? null),
                'end_time' => $allDay ? null : ($data['end_time'] ?? null),
                'is_recurring' => false,
            ]);

            return $blackoutDate;
        } catch (Exception $e) {
            throw new Exception('Failed to add unavailable date: ' . $e->getMessage());
        }
    }

    /**
     * Delete unavailable date
     */
    public function deleteUnavailableDate(BlackoutDate $blackoutDate): bool
    {
        try {
            return $blackoutDate->delete();
        } catch (Exception $e) {
            throw new Exception('Failed to delete unavailable date: ' . $e->getMessage());
        }
    }
}
