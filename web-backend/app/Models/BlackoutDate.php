<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlackoutDate extends Model
{
    protected $fillable = [
        'date',
        'reason',
        'start_time',
        'end_time',
        'is_recurring',
        'recurring_days'
    ];

    protected $casts = [
        'is_recurring' => 'boolean',
        'recurring_days' => 'array',
        'date' => 'date',
    ];
}
