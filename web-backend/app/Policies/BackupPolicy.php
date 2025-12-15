<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DatabaseBackup;
use Illuminate\Auth\Access\HandlesAuthorization;

class BackupPolicy
{
    use HandlesAuthorization;

    /**
     * Only super admins can view backup list
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only super admins can view individual backups
     */
    public function view(User $user, DatabaseBackup $backup): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only super admins can create backups
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only super admins can restore from backups - critical operation
     */
    public function restore(User $user, DatabaseBackup $backup): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only super admins can delete backups
     */
    public function delete(User $user, DatabaseBackup $backup): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only super admins can verify backups
     */
    public function verify(User $user, DatabaseBackup $backup): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }

    /**
     * Only super admins can cleanup old backups
     */
    public function cleanup(User $user): bool
    {
        return $user->role === 'admin' && $user->is_active;
    }
}
