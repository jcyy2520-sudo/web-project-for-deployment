<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MlOutcomeLog extends Model
{
    protected $fillable = [
        'appointment_id',
        'prediction_type',
        'predicted_outcome',
        'predicted_probability',
        'actual_outcome',
        'staff_feedback',
        'staff_feedback_reason',
        'logged_by',
    ];

    protected $casts = [
        'predicted_probability' => 'float',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function logger()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
