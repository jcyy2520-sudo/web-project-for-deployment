<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Exception;

/**
 * UserService: Handles all user-related business logic
 * Responsibilities: User creation, updates, deletions, status management
 */
class UserService
{
    /**
     * Create a new user
     */
    public function createUser(array $data): User
    {
        try {
            $user = new User([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            // Set sensitive fields explicitly (they are excluded from $fillable)
            $user->password = bcrypt($data['password']);
            $user->role = $data['role'] ?? 'client';
            $user->is_active = $data['is_active'] ?? true;
            $user->save();

            return $user;
        } catch (Exception $e) {
            throw new Exception('Failed to create user: ' . $e->getMessage());
        }
    }

    /**
     * Update user information
     */
    public function updateUser(User $user, array $data): User
    {
        try {
            $user->update([
                'first_name' => $data['first_name'] ?? $user->first_name,
                'last_name' => $data['last_name'] ?? $user->last_name,
                'email' => $data['email'] ?? $user->email,
                'phone' => $data['phone'] ?? $user->phone,
                'address' => $data['address'] ?? $user->address,
                'role' => $data['role'] ?? $user->role,
                'is_active' => $data['is_active'] ?? $user->is_active,
            ]);

            if (isset($data['password'])) {
                $user->update(['password' => bcrypt($data['password'])]);
            }

            return $user;
        } catch (Exception $e) {
            throw new Exception('Failed to update user: ' . $e->getMessage());
        }
    }

    /**
     * Toggle user active status
     */
    public function toggleUserStatus(User $user): bool
    {
        try {
            $user->update(['is_active' => !$user->is_active]);
            return true;
        } catch (Exception $e) {
            throw new Exception('Failed to toggle user status: ' . $e->getMessage());
        }
    }

    /**
     * Delete user
     */
    public function deleteUser(User $user): bool
    {
        try {
            return $user->delete();
        } catch (Exception $e) {
            throw new Exception('Failed to delete user: ' . $e->getMessage());
        }
    }

    /**
     * Get users by role
     */
    public function getUsersByRole(string $role): Collection
    {
        try {
            return User::where('role', $role)->get();
        } catch (Exception $e) {
            throw new Exception('Failed to fetch users: ' . $e->getMessage());
        }
    }

    /**
     * Search users
     */
    public function searchUsers(string $searchTerm): Collection
    {
        try {
            return User::where('first_name', 'like', "%{$searchTerm}%")
                ->orWhere('last_name', 'like', "%{$searchTerm}%")
                ->orWhere('email', 'like', "%{$searchTerm}%")
                ->orWhere('phone', 'like', "%{$searchTerm}%")
                ->get();
        } catch (Exception $e) {
            throw new Exception('Failed to search users: ' . $e->getMessage());
        }
    }
}
