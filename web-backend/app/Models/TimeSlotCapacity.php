<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeSlotCapacity extends Model
{
    protected $fillable = [
        'day_of_week',
        'specific_date',
        'scope_key',
        'start_time',
        'end_time',
        'max_appointments_per_slot',
        'is_active',
        'description'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_appointments_per_slot' => 'integer',
        'specific_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $timeSlotCapacity): void {
            $timeSlotCapacity->normalizeScope();
            $timeSlotCapacity->scope_key = self::makeScopeKey(
                $timeSlotCapacity->day_of_week,
                $timeSlotCapacity->specific_date
            );
        });
    }

    public static function makeScopeKey(?string $dayOfWeek, $specificDate = null): string
    {
        if ($specificDate instanceof \DateTimeInterface) {
            $specificDate = $specificDate->format('Y-m-d');
        }

        if ($specificDate) {
            return 'date:' . $specificDate;
        }

        $normalizedDayOfWeek = $dayOfWeek !== null && $dayOfWeek !== ''
            ? strtolower($dayOfWeek)
            : null;

        return $normalizedDayOfWeek ? 'day:' . $normalizedDayOfWeek : 'all';
    }

    public function normalizeScope(): void
    {
        $this->day_of_week = $this->day_of_week !== null && $this->day_of_week !== ''
            ? strtolower($this->day_of_week)
            : null;

        if ($this->specific_date) {
            $this->day_of_week = null;
        }
    }
}
