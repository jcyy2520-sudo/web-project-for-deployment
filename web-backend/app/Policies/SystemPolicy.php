<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SystemPolicy
{
    use HandlesAuthorization;

    /**
     * Only admins can access system settings
     */
    public function accessSettings(User $user): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only admins can view system monitoring dashboard
     */
    public function viewMonitoring(User $user): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only admins can perform system maintenance
     */
    public function performMaintenance(User $user): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only admins can clear cache
     */
    public function clearCache(User $user): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only admins can view security logs and events
     */
    public function viewSecurityLogs(User $user): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only admins can manage IP blocking and rate limiting
     */
    public function manageSecurityRules(User $user): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only admins can rotate or view sensitive logs
     */
    public function rotateLogs(User $user): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }
}
