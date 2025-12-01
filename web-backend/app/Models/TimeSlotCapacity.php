<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeSlotCapacity extends Model
{
    protected $fillable = [
        'day_of_week',
        'start_time',
        'end_time',
        'max_appointments_per_slot',
        'is_active',
        'description'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_appointments_per_slot' => 'integer',
    ];
}
