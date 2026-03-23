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
        'is_active',
        'public_requirements',
        'internal_staff_notes'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'public_requirements' => 'array'
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

    public function unavailabilities()
    {
        return $this->hasMany(ServiceUnavailability::class);
    }

    public function activeUnavailabilities()
    {
        return $this->hasMany(ServiceUnavailability::class)->currentlyActive();
    }

    /**
     * Check if this service is currently unavailable.
     * Returns the active unavailability record if unavailable, null otherwise.
     */
    public function getCurrentUnavailability(): ?ServiceUnavailability
    {
        return ServiceUnavailability::isServiceUnavailableAt($this->id);
    }

    /**
     * Check if this service is available for booking at a given datetime.
     */
    public function isAvailableAt(?\Carbon\Carbon $dateTime = null): bool
    {
        return ServiceUnavailability::isServiceUnavailableAt($this->id, $dateTime) === null;
    }

    public static function getServiceStats()
    {
        return self::withCount('appointments')
            ->orderBy('appointments_count', 'desc')
            ->get();
    }
}
