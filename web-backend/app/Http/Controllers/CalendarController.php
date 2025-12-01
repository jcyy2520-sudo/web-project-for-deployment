<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\TimeSlotCapacity;
use App\Models\BlackoutDate;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $events = CalendarEvent::whereBetween('event_date', [
            $request->start_date,
            $request->end_date
        ])->get();

        return response()->json($events);
    }

    public function store(Request $request)
    {
        $request->validate([
            'event_date' => 'required|date',
            'type' => 'required|in:available,unavailable,holiday',
            'reason' => 'nullable|string|max:500',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'is_recurring' => 'boolean',
            'recurring_days' => 'nullable|array',
        ]);

        $event = CalendarEvent::create($request->all());

        return response()->json([
            'message' => 'Calendar event created successfully',
            'event' => $event
        ]);
    }

    public function update(Request $request, CalendarEvent $calendarEvent)
    {
        $request->validate([
            'event_date' => 'required|date',
            'type' => 'required|in:available,unavailable,holiday',
            'reason' => 'nullable|string|max:500',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'is_recurring' => 'boolean',
            'recurring_days' => 'nullable|array',
        ]);

        $calendarEvent->update($request->all());

        return response()->json([
            'message' => 'Calendar event updated successfully',
            'event' => $calendarEvent
        ]);
    }

    public function destroy(CalendarEvent $calendarEvent)
    {
        $calendarEvent->delete();

        return response()->json([
            'message' => 'Calendar event deleted successfully'
        ]);
    }

    /**
     * Get available slots with new booking rules
     * - No bookings on Saturday (6) and Sunday (0)
     * - Time: 8 AM to 5 PM
     * - Lunch break: 12 PM to 1 PM (not available)
     * - Capacity: Max appointments per hour
     */
    public function getAvailableSlots(Request $request)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
        ]);

        $date = Carbon::parse($request->date);
        $dayOfWeek = $date->dayOfWeek; // 0 = Sunday, 6 = Saturday

        // Rule 1: Block weekends (Saturday = 6, Sunday = 0)
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            return response()->json([
                'date' => $request->date,
                'available_slots' => [],
                'message' => 'Not available on weekends',
                'blocked_reason' => 'Closed on weekends'
            ]);
        }

        // Rule 2: Check blackout dates
        $blackoutDate = $this->getBlackoutDate($request->date);
        if ($blackoutDate && $blackoutDate['blocks_entire_day']) {
            return response()->json([
                'date' => $request->date,
                'available_slots' => [],
                'message' => 'Date is not available',
                'blocked_reason' => $blackoutDate['reason'] ?? 'This date is not available for bookings'
            ]);
        }

        // Rule 3: Generate time slots (8 AM to 5 PM, excluding 12-1 PM lunch)
        $slots = $this->generateWorkingHourSlots($request->date);

        // Rule 4: Check blackout times
        if ($blackoutDate && !$blackoutDate['blocks_entire_day']) {
            $slots = $this->filterByBlackoutTimes($slots, $blackoutDate);
        }

        // Rule 5: Remove booked slots
        $bookedSlots = Appointment::where('appointment_date', $request->date)
            ->whereIn('status', ['pending', 'approved'])
            ->pluck('appointment_time')
            ->toArray();

        // Rule 6: Check capacity limits per slot
        $availableSlots = [];
        foreach ($slots as $slot) {
            if (!in_array($slot, $bookedSlots)) {
                $appointmentCount = Appointment::where('appointment_date', $request->date)
                    ->where('appointment_time', $slot)
                    ->whereIn('status', ['pending', 'approved'])
                    ->count();

                $maxCapacity = $this->getSlotCapacity($request->date, $slot);
                
                if ($appointmentCount < $maxCapacity) {
                    $availableSlots[] = [
                        'time' => $slot,
                        'booked' => $appointmentCount,
                        'capacity' => $maxCapacity,
                        'availability' => $maxCapacity - $appointmentCount
                    ];
                }
            }
        }

        return response()->json([
            'date' => $request->date,
            'available_slots' => array_column($availableSlots, 'time'),
            'slot_details' => $availableSlots,
            'total_available' => count($availableSlots),
            'blackout_dates' => null,
            'success' => true
        ]);
    }

    /**
     * Get all unavailable/blackout dates for a date range
     */
    public function getUnavailableDates(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $unavailableDates = [];

        // Add weekends
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dayOfWeek = $currentDate->dayOfWeek;
            
            // Block weekends
            if ($dayOfWeek === 0 || $dayOfWeek === 6) {
                $unavailableDates[] = [
                    'date' => $currentDate->toDateString(),
                    'reason' => $currentDate->dayName . ' - Closed',
                    'type' => 'weekend'
                ];
            }
            
            $currentDate->addDay();
        }

        // Add blackout dates
        $blackoutDates = BlackoutDate::whereBetween('date', [
            $request->start_date,
            $request->end_date
        ])->orWhere('is_recurring', true)
          ->get();

        foreach ($blackoutDates as $blackout) {
            if ($blackout->is_recurring && $blackout->recurring_days) {
                $currentDate = $startDate->copy();
                while ($currentDate <= $endDate) {
                    $dayName = strtolower($currentDate->englishDayOfWeek);
                    if (in_array($dayName, $blackout->recurring_days)) {
                        $unavailableDates[] = [
                            'date' => $currentDate->toDateString(),
                            'reason' => $blackout->reason ?? 'Not available',
                            'type' => 'blackout',
                            'recurring' => true
                        ];
                    }
                    $currentDate->addDay();
                }
            } else {
                $unavailableDates[] = [
                    'date' => $blackout->date->toDateString(),
                    'reason' => $blackout->reason ?? 'Not available',
                    'type' => 'blackout',
                    'time_range' => $blackout->start_time && $blackout->end_time 
                        ? "{$blackout->start_time} - {$blackout->end_time}"
                        : 'All day'
                ];
            }
        }

        return response()->json([
            'success' => true,
            'unavailable_dates' => array_values(array_unique($unavailableDates, SORT_REGULAR)),
            'total_unavailable' => count(array_unique($unavailableDates, SORT_REGULAR))
        ]);
    }

    /**
     * Get time slot capacity information
     */
    public function getSlotCapacities(Request $request)
    {
        $request->validate([
            'date' => 'nullable|date',
        ]);

        $date = $request->date ? Carbon::parse($request->date) : null;
        $dayName = $date ? strtolower($date->englishDayOfWeek) : null;

        $capacities = TimeSlotCapacity::where('is_active', true)
            ->where(function ($query) use ($dayName) {
                $query->whereNull('day_of_week')
                      ->orWhere('day_of_week', $dayName);
            })
            ->get();

        return response()->json([
            'success' => true,
            'date' => $request->date,
            'day_of_week' => $dayName,
            'capacities' => $capacities
        ]);
    }

    /**
     * Private helper methods
     */

    private function generateWorkingHourSlots($date)
    {
        $slots = [];
        $current = Carbon::parse($date . ' 08:00'); // 8 AM
        $end = Carbon::parse($date . ' 17:00');     // 5 PM

        while ($current <= $end) {
            $timeStr = $current->format('H:i');
            
            // Skip lunch time (12:00 - 13:00)
            if (!($current->hour >= 12 && $current->hour < 13)) {
                $slots[] = $timeStr;
            }

            $current->addMinutes(30);
        }

        return $slots;
    }

    private function getBlackoutDate($date)
    {
        $parsedDate = Carbon::parse($date);
        $dayName = strtolower($parsedDate->englishDayOfWeek);

        // Check specific date blackout
        $blackout = BlackoutDate::where('date', $date)
            ->where('is_recurring', false)
            ->first();

        if ($blackout) {
            return [
                'date' => $date,
                'reason' => $blackout->reason,
                'blocks_entire_day' => !$blackout->start_time || !$blackout->end_time,
                'start_time' => $blackout->start_time,
                'end_time' => $blackout->end_time
            ];
        }

        // Check recurring blackout
        $recurringBlackout = BlackoutDate::where('is_recurring', true)
            ->whereJsonContains('recurring_days', $dayName)
            ->first();

        if ($recurringBlackout) {
            return [
                'date' => $date,
                'reason' => $recurringBlackout->reason,
                'blocks_entire_day' => !$recurringBlackout->start_time || !$recurringBlackout->end_time,
                'start_time' => $recurringBlackout->start_time,
                'end_time' => $recurringBlackout->end_time,
                'recurring' => true
            ];
        }

        return null;
    }

    private function filterByBlackoutTimes($slots, $blackoutData)
    {
        if (!$blackoutData['start_time'] || !$blackoutData['end_time']) {
            return []; // Entire day blocked
        }

        return array_filter($slots, function ($slot) use ($blackoutData) {
            $slotTime = strtotime($slot);
            $startTime = strtotime($blackoutData['start_time']);
            $endTime = strtotime($blackoutData['end_time']);

            return $slotTime < $startTime || $slotTime >= $endTime;
        });
    }

    private function getSlotCapacity($date, $time)
    {
        $parsedDate = Carbon::parse($date);
        $dayName = strtolower($parsedDate->englishDayOfWeek);
        $slotTime = Carbon::parse($time);

        // Find matching capacity rule
        $capacity = TimeSlotCapacity::where('is_active', true)
            ->where(function ($query) use ($dayName) {
                $query->whereNull('day_of_week')
                      ->orWhere('day_of_week', $dayName);
            })
            ->where('start_time', '<=', $time)
            ->where('end_time', '>', $time)
            ->first();

        return $capacity ? $capacity->max_appointments_per_slot : 3; // Default 3 per slot
    }

    private function generateTimeSlots($start, $end, $interval = '30 minutes')
    {
        $slots = [];
        $current = Carbon::parse($start);
        $end = Carbon::parse($end);

        while ($current < $end) {
            $slots[] = $current->format('H:i');
            $current->add($interval);
        }

        return $slots;
    }
}
