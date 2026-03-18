<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'price',
        'duration',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // Relationship to appointments using service_id foreign key (legacy)
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'service_id');
    }

    // New many-to-many relationship
    public function manyAppointments()
    {
        return $this->belongsToMany(Appointment::class, 'appointment_service')
                    ->withPivot('price_at_booking')
                    ->withTimestamps();
    }

    public static function getServiceStats()
    {
        return self::withCount('appointments')
            ->orderBy('appointments_count', 'desc')
            ->get();
    }
}
