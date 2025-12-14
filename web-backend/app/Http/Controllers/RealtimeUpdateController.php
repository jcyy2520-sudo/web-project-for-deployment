<?php

namespace App\Http\Controllers;

use App\Models\TimeSlotCapacity;
use App\Models\AppointmentSettings;
use Illuminate\Http\Request;

class RealtimeUpdateController extends Controller
{
    /**
     * Get latest updates for slot capacities and appointment settings
     * Used for polling when WebSocket is not available
     * GET /api/realtime/updates?last_check={timestamp}
     */
    public function getUpdates(Request $request)
    {
        try {
            $lastCheck = $request->query('last_check');
            $lastCheckTime = $lastCheck ? \Carbon\Carbon::parse($lastCheck) : now()->subMinutes(5);

            // Get latest slot capacity changes
            $slotCapacities = TimeSlotCapacity::where('updated_at', '>', $lastCheckTime)
                ->orderBy('updated_at', 'desc')
                ->get(['id', 'start_time', 'end_time', 'max_appointments_per_slot', 'day_of_week', 'updated_at']);

            // Get latest appointment settings changes
            $appointmentSettings = AppointmentSettings::where('updated_at', '>', $lastCheckTime)
                ->where('is_active', true)
                ->first();

            return response()->json([
                'success' => true,
                'timestamp' => now()->toIso8601String(),
                'changes' => [
                    'slot_capacities' => [
                        'count' => $slotCapacities->count(),
                        'data' => $slotCapacities
                    ],
                    'appointment_settings' => $appointmentSettings ? [
                        'updated' => true,
                        'data' => $appointmentSettings
                    ] : [
                        'updated' => false,
                        'data' => null
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching updates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific slot capacity data for a time range
     * GET /api/realtime/slot-capacities?start_time={time}&end_time={time}&date={date}
     */
    public function getSlotCapacityData(Request $request)
    {
        try {
            $request->validate([
                'date' => 'required|date_format:Y-m-d',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
            ]);

            $date = $request->query('date');
            $startTime = $request->query('start_time');
            $endTime = $request->query('end_time');

            // Get day of week for the date
            $dayOfWeek = \Carbon\Carbon::parse($date)->englishDayOfWeek;
            $dayOfWeek = strtolower($dayOfWeek);

            $query = TimeSlotCapacity::where('is_active', true)
                ->where(function ($q) use ($dayOfWeek) {
                    $q->whereNull('day_of_week')
                      ->orWhere('day_of_week', $dayOfWeek);
                });

            if ($startTime) {
                $query->where('start_time', '>=', $startTime);
            }

            if ($endTime) {
                $query->where('end_time', '<=', $endTime);
            }

            $capacities = $query->orderBy('start_time')->get();

            return response()->json([
                'success' => true,
                'date' => $date,
                'day_of_week' => $dayOfWeek,
                'data' => $capacities,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching slot capacity data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get appointment settings
     * GET /api/realtime/appointment-settings
     */
    public function getAppointmentSettings(Request $request)
    {
        try {
            $settings = AppointmentSettings::where('is_active', true)->first();

            if (!$settings) {
                $settings = AppointmentSettings::getCurrent();
            }

            return response()->json([
                'success' => true,
                'data' => $settings,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching appointment settings: ' . $e->getMessage()
            ], 500);
        }
    }
}
