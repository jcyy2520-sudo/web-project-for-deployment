<?php

namespace App\Http\Controllers;

use App\Models\TimeSlotCapacity;
use App\Events\SlotCapacityChanged;
use App\Models\ActionLog;
use App\Traits\SafeExperimentalFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                $payload = $this->buildSlotCapacityPayload($request);
                $scopeKey = TimeSlotCapacity::makeScopeKey($payload['day_of_week'], $payload['specific_date']);

                [$capacity, $action] = DB::transaction(function () use ($payload, $scopeKey) {
                    $existing = TimeSlotCapacity::where('scope_key', $scopeKey)
                        ->where('start_time', $payload['start_time'])
                        ->where('end_time', $payload['end_time'])
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        $existing->update($payload + ['is_active' => true]);

                        return [$existing, 'updated'];
                    }

                    return [TimeSlotCapacity::create($payload), 'created'];
                });
                
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
                'specific_date' => 'nullable|date|after_or_equal:today',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'max_appointments_per_slot' => 'required|integer|min:1|max:20',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:500',
            ]);

            try {
                $payload = $this->buildSlotCapacityPayload($request, $timeSlotCapacity);
                $scopeKey = TimeSlotCapacity::makeScopeKey($payload['day_of_week'], $payload['specific_date']);

                $conflictExists = TimeSlotCapacity::where('scope_key', $scopeKey)
                    ->where('start_time', $payload['start_time'])
                    ->where('end_time', $payload['end_time'])
                    ->whereKeyNot($timeSlotCapacity->id)
                    ->exists();

                if ($conflictExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A slot capacity configuration already exists for this scope and time range.'
                    ], 422);
                }

                $timeSlotCapacity->update($payload);
                
                // Broadcast the change to all connected clients
                try {
                    broadcast(new SlotCapacityChanged($timeSlotCapacity, 'updated', [$timeSlotCapacity->start_time]));
                } catch (\Exception $e) {
                    \Log::warning('Failed to broadcast slot capacity update: ' . $e->getMessage());
                }

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
                try {
                    broadcast(new SlotCapacityChanged($timeSlotCapacity, 'deleted', [$affectedTime]));
                } catch (\Exception $e) {
                    \Log::warning('Failed to broadcast slot capacity deletion: ' . $e->getMessage());
                }

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
                $scopeKey = TimeSlotCapacity::makeScopeKey(null, null);

                foreach ($timeSlots as [$startTime, $endTime]) {
                    $slot = TimeSlotCapacity::updateOrCreate(
                        [
                            'scope_key' => $scopeKey,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                        ],
                        [
                            'day_of_week' => null,
                            'specific_date' => null,
                            'max_appointments_per_slot' => $capacity,
                            'is_active' => true,
                        ]
                    );

                    if ($slot->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                }

                // Broadcast the change to all connected clients with all affected hours
                try {
                    broadcast(new SlotCapacityChanged(
                        (object)['max_appointments_per_slot' => $capacity],
                        'apply_all',
                        array_column($timeSlots, 0) // All start times
                    ));
                } catch (\Exception $e) {
                    \Log::warning('Failed to broadcast bulk slot capacity change: ' . $e->getMessage());
                }

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

    private function buildSlotCapacityPayload(Request $request, ?TimeSlotCapacity $existing = null): array
    {
        $specificDate = $request->has('specific_date')
            ? ($request->filled('specific_date') ? $request->specific_date : null)
            : ($existing?->specific_date?->format('Y-m-d'));

        $dayOfWeek = $request->has('day_of_week')
            ? ($request->filled('day_of_week') ? strtolower($request->day_of_week) : null)
            : $existing?->day_of_week;

        if ($specificDate) {
            $dayOfWeek = null;
        }

        return [
            'day_of_week' => $dayOfWeek,
            'specific_date' => $specificDate,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'max_appointments_per_slot' => $request->max_appointments_per_slot,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : ($existing?->is_active ?? true),
            'description' => $request->input('description', $existing?->description),
        ];
    }
}
