<?php

namespace App\Http\Controllers;

use App\Models\TimeSlotCapacity;
use App\Models\AppointmentSettings;
use App\Models\BlackoutDate;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
            $userId = $request->user() ? $request->user()->id : 0;

            // Cache the result for 5 seconds to reduce DB load from frequent polling
            $cacheKey = 'realtime_updates_' . $userId . '_' . $lastCheckTime->timestamp;
            
            $result = Cache::remember($cacheKey, 5, function () use ($lastCheckTime, $request) {

                // Get latest slot capacity changes
                $slotCapacities = TimeSlotCapacity::where('updated_at', '>', $lastCheckTime)
                    ->orderBy('updated_at', 'desc')
                    ->get(['id', 'start_time', 'end_time', 'max_appointments_per_slot', 'day_of_week', 'updated_at']);

                // Get latest appointment settings changes
                $appointmentSettings = AppointmentSettings::where('updated_at', '>', $lastCheckTime)
                    ->where('is_active', true)
                    ->first();

                // Check for unavailable/blackout date changes
                $unavailableDatesChanged = false;
                $lastUnavailableUpdate = Cache::get('unavailable_dates_last_update');
                if ($lastUnavailableUpdate) {
                    $lastUnavailableTime = \Carbon\Carbon::parse($lastUnavailableUpdate);
                    $unavailableDatesChanged = $lastUnavailableTime->gt($lastCheckTime);
                }
                // Also check database directly for recent blackout date changes
                if (!$unavailableDatesChanged) {
                    $recentBlackout = BlackoutDate::where('updated_at', '>', $lastCheckTime)->exists();
                    $unavailableDatesChanged = $recentBlackout;
                }

                // Check if the authenticated user's appointments have been updated (status changed by admin)
                $appointmentsChanged = false;
                $user = $request->user();
                if ($user) {
                    $appointmentsChanged = Appointment::where('user_id', $user->id)
                        ->where('updated_at', '>', $lastCheckTime)
                        ->exists();
                }

                return [
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
                        ],
                        'unavailable_dates' => [
                            'updated' => $unavailableDatesChanged,
                        ],
                        'appointments' => [
                            'updated' => $appointmentsChanged,
                        ]
                    ]
                ];
            });

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Error fetching updates: ' . $e->getMessage() : 'Error fetching updates'
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
                'message' => config('app.debug') ? 'Error fetching slot capacity data: ' . $e->getMessage() : 'Error fetching slot capacity data'
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
                'message' => config('app.debug') ? 'Error fetching appointment settings: ' . $e->getMessage() : 'Error fetching appointment settings'
            ], 500);
        }
    }
}
