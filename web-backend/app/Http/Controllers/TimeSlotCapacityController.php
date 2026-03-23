<?php

namespace App\Http\Controllers;

use App\Models\TimeSlotCapacity;
use App\Events\SlotCapacityChanged;
use App\Models\ActionLog;
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

        if ($request->has('specific_date')) {
            $query->where('specific_date', $request->specific_date);
        } elseif (!$request->boolean('include_date_overrides', false)) {
            $query->whereNull('specific_date');
        }

        $capacities = $query->orderBy('specific_date', 'desc')->orderBy('start_time')->get();

        return response()->json([
            'success' => true,
            'data' => $capacities,
            'total' => count($capacities)
        ]);
    }

    /**
     * Create or update a slot capacity configuration (upsert)
     */
    public function store(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'day_of_week' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'specific_date' => 'nullable|date|after_or_equal:today',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'max_appointments_per_slot' => 'required|integer|min:1|max:20',
                'description' => 'nullable|string|max:500',
            ]);

            try {
                // Upsert: update if exists, create if not
                $existing = TimeSlotCapacity::where('start_time', $request->start_time)
                    ->where('end_time', $request->end_time)
                    ->where(function ($q) use ($request) {
                        if ($request->specific_date) {
                            $q->where('specific_date', $request->specific_date);
                        } else {
                            $q->whereNull('specific_date');
                            if ($request->day_of_week) {
                                $q->where('day_of_week', $request->day_of_week);
                            } else {
                                $q->whereNull('day_of_week');
                            }
                        }
                    })
                    ->first();

                if ($existing) {
                    $existing->update([
                        'max_appointments_per_slot' => $request->max_appointments_per_slot,
                        'description' => $request->description,
                        'is_active' => true,
                    ]);
                    $capacity = $existing;
                    $action = 'updated';
                } else {
                    $capacity = TimeSlotCapacity::create($request->all());
                    $action = 'created';
                }
                
                // Broadcast the change to all connected clients
                try {
                    broadcast(new SlotCapacityChanged($capacity, $action, [$capacity->start_time]));
                } catch (\Exception $e) {
                    \Log::warning('Failed to broadcast slot capacity change: ' . $e->getMessage());
                }

                ActionLog::log($action === 'created' ? 'create' : 'update', ucfirst($action) . " slot capacity: {$capacity->start_time}-{$capacity->end_time} (max: {$capacity->max_appointments_per_slot})", 'TimeSlotCapacity', $capacity->id);

                return response()->json([
                    'success' => true,
                    'message' => "Slot capacity configuration {$action} successfully",
                    'data' => $capacity
                ], $action === 'created' ? 201 : 200);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error saving slot capacity: ' . $e->getMessage() : 'Error saving slot capacity'
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
                
                // Broadcast the change to all connected clients
                broadcast(new SlotCapacityChanged($timeSlotCapacity, 'updated', [$timeSlotCapacity->start_time]));

                ActionLog::log('update', "Updated slot capacity: {$timeSlotCapacity->start_time}-{$timeSlotCapacity->end_time} (max: {$timeSlotCapacity->max_appointments_per_slot})", 'TimeSlotCapacity', $timeSlotCapacity->id);

                return response()->json([
                    'success' => true,
                    'message' => 'Slot capacity configuration updated successfully',
                    'data' => $timeSlotCapacity
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error updating slot capacity: ' . $e->getMessage() : 'Error updating slot capacity'
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
                $affectedTime = $timeSlotCapacity->start_time;
                $timeSlotCapacity->delete();
                
                // Broadcast the change to all connected clients
                broadcast(new SlotCapacityChanged($timeSlotCapacity, 'deleted', [$affectedTime]));

                ActionLog::log('delete', "Deleted slot capacity for {$affectedTime}", 'TimeSlotCapacity', $timeSlotCapacity->id);

                return response()->json([
                    'success' => true,
                    'message' => 'Slot capacity configuration deleted successfully'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error deleting slot capacity: ' . $e->getMessage() : 'Error deleting slot capacity'
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
                        ->whereNull('specific_date')
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

                // Broadcast the change to all connected clients with all affected hours
                broadcast(new SlotCapacityChanged(
                    (object)['max_appointments_per_slot' => $capacity],
                    'apply_all',
                    array_column($timeSlots, 0) // All start times
                ));

                ActionLog::log('update', "Applied capacity {$capacity} to all time slots (Created: {$created}, Updated: {$updated})", 'TimeSlotCapacity', null);

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
                    'message' => config('app.debug') ? 'Error applying capacity to all slots: ' . $e->getMessage() : 'Error applying capacity to all slots'
                ], 500);
            }
        }, 'slot_capacity.apply_all');
    }
}
