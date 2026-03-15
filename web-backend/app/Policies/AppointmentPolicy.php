<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppointmentPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Appointment $appointment)
    {
        return $user->isAdmin() || 
               $user->isStaff() || 
               $appointment->user_id === $user->id;
    }

    public function update(User $user, Appointment $appointment)
    {
        return $user->isAdmin() || $user->isStaff();
    }

    public function delete(User $user, Appointment $appointment)
    {
        return $user->isAdmin() || $appointment->user_id === $user->id;
    }
}