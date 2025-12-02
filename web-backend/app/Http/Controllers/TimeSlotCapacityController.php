<?php

namespace App\Http\Controllers;

use App\Models\TimeSlotCapacity;
use App\Traits\SafeExperimentalFeature;
use Illuminate\Http\Request;

class TimeSlotCapacityController extends Controller
{
    use SafeExperimentalFeature;
    /**
     * Get all slot capacity configurations
     */
    public function index(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $query = TimeSlotCapacity::query();

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('day_of_week')) {
                $query->where(function ($q) use ($request) {
                    $q->whereNull('day_of_week')
                      ->orWhere('day_of_week', $request->day_of_week);
                });
            }

            $capacities = $query->orderBy('start_time')->get();

            return response()->json([
                'success' => true,
                'data' => $capacities,
                'total' => count($capacities)
            ]);
        }, 'slot_capacity.index');
    }

    /**
     * Create a new slot capacity configuration
     */
    public function store(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'day_of_week' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'max_appointments_per_slot' => 'required|integer|min:1|max:20',
                'description' => 'nullable|string|max:500',
            ]);

            try {
                $capacity = TimeSlotCapacity::create($request->all());

                return response()->json([
                    'success' => true,
                    'message' => 'Slot capacity configuration created successfully',
                    'data' => $capacity
                ], 201);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error creating slot capacity: ' . $e->getMessage()
                ], 500);
            }
        }, 'slot_capacity.store');
    }

    /**
     * Update a slot capacity configuration
     */
    public function update(Request $request, TimeSlotCapacity $timeSlotCapacity)
    {
        return $this->wrapExperimental(function () use ($request, $timeSlotCapacity) {
            $request->validate([
                'day_of_week' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'max_appointments_per_slot' => 'required|integer|min:1|max:20',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:500',
            ]);

            try {
                $timeSlotCapacity->update($request->all());

                return response()->json([
                    'success' => true,
                    'message' => 'Slot capacity configuration updated successfully',
                    'data' => $timeSlotCapacity
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error updating slot capacity: ' . $e->getMessage()
                ], 500);
            }
        }, 'slot_capacity.update');
    }

    /**
     * Delete a slot capacity configuration
     */
    public function destroy(TimeSlotCapacity $timeSlotCapacity)
    {
        return $this->wrapExperimental(function () use ($timeSlotCapacity) {
            try {
                $timeSlotCapacity->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Slot capacity configuration deleted successfully'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error deleting slot capacity: ' . $e->getMessage()
                ], 500);
            }
        }, 'slot_capacity.destroy');
    }

    /**
     * Get capacity summary for a specific date/time range
     */
    public function getCapacitySummary(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'day_of_week' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            ]);

            $query = TimeSlotCapacity::where('is_active', true)
                ->where('start_time', '<=', $request->start_time)
                ->where('end_time', '>', $request->start_time);

            if ($request->has('day_of_week')) {
                $query->where(function ($q) use ($request) {
                    $q->whereNull('day_of_week')
                      ->orWhere('day_of_week', $request->day_of_week);
                });
            }

            $capacity = $query->first();

            return response()->json([
                'success' => true,
                'capacity' => $capacity ? $capacity->max_appointments_per_slot : 3,
                'time_range' => "{$request->start_time} - {$request->end_time}",
                'configuration' => $capacity
            ]);
        }, 'slot_capacity.summary');
    }

    /**
     * Apply capacity to all available time slots at once
     */
    public function applyAll(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'max_appointments_per_slot' => 'required|integer|min:1|max:20',
            ]);

            try {
                // Define all available time slots (8 AM - 5 PM, excluding lunch 12-1 PM)
                $timeSlots = [
                    ['08:00', '08:30'],
                    ['08:30', '09:00'],
                    ['09:00', '09:30'],
                    ['09:30', '10:00'],
                    ['10:00', '10:30'],
                    ['10:30', '11:00'],
                    ['11:00', '11:30'],
                    ['11:30', '12:00'],
                    // Lunch break (12:00-13:00) excluded
                    ['13:00', '13:30'],
                    ['13:30', '14:00'],
                    ['14:00', '14:30'],
                    ['14:30', '15:00'],
                    ['15:00', '15:30'],
                    ['15:30', '16:00'],
                    ['16:00', '16:30'],
                    ['16:30', '17:00'],
                ];

                $capacity = $request->max_appointments_per_slot;
                $created = 0;
                $updated = 0;

                foreach ($timeSlots as [$startTime, $endTime]) {
                    $existingCapacity = TimeSlotCapacity::where('start_time', $startTime)
                        ->where('end_time', $endTime)
                        ->whereNull('day_of_week')
                        ->first();

                    if ($existingCapacity) {
                        $existingCapacity->update([
                            'max_appointments_per_slot' => $capacity
                        ]);
                        $updated++;
                    } else {
                        TimeSlotCapacity::create([
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'day_of_week' => null,
                            'max_appointments_per_slot' => $capacity,
                            'is_active' => true
                        ]);
                        $created++;
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => "Capacity applied to all time slots (Created: {$created}, Updated: {$updated})",
                    'data' => [
                        'created' => $created,
                        'updated' => $updated,
                        'total' => count($timeSlots)
                    ]
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error applying capacity to all slots: ' . $e->getMessage()
                ], 500);
            }
        }, 'slot_capacity.apply_all');
    }
}
