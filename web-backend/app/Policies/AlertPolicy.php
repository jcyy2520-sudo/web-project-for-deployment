<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AlertRule;
use Illuminate\Auth\Access\HandlesAuthorization;

class AlertPolicy
{
    use HandlesAuthorization;

    /**
     * Only admins can view alert rules
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only admins can view individual alert rules
     */
    public function view(User $user, AlertRule $alert): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only admins can create alert rules
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only admins can update alert rules
     */
    public function update(User $user, AlertRule $alert): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only admins can delete alert rules
     */
    public function delete(User $user, AlertRule $alert): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only admins can acknowledge alerts
     */
    public function acknowledge(User $user, AlertRule $alert): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }
}
