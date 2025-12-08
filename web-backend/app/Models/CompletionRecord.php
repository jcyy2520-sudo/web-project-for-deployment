<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompletionRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'completed_by',
        'outcome_status',
        'duration_minutes',
        'work_done',
        'notes'
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
