<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Create cache key based on query parameters
        $cacheKey = 'users_index_' . md5(json_encode($request->all()));
        
        // Cache the result for 60 seconds
        $result = Cache::remember($cacheKey, 60, function () use ($request) {
            $query = User::query()
                ->select([
                    'id', 'username', 'email', 'first_name', 'last_name', 
                    'phone', 'role', 'is_active', 'created_at', 'updated_at'
                ]); // OPTIMIZATION: Only select needed columns

            if ($request->has('role') && $request->role !== 'all') {
                $query->where('role', $request->role);
            }

            if ($request->has('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('username', 'like', '%' . $request->search . '%')
                      ->orWhere('email', 'like', '%' . $request->search . '%')
                      ->orWhere('first_name', 'like', '%' . $request->search . '%')
                      ->orWhere('last_name', 'like', '%' . $request->search . '%');
                });
            }

            $users = $query->where('id', '!=', $request->user()->id)
                          ->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 10));

            return [
                'data' => $users->items(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
                'success' => true
            ];
        });

        return response()->json($result);
    }

    // NEW METHOD FOR ADMIN DASHBOARD ROLE FILTERING
    public function getUsersByRole(Request $request)
    {
        $query = User::query()
            ->select([
                'id', 'username', 'email', 'first_name', 'last_name', 
                'phone', 'role', 'is_active', 'created_at', 'updated_at', 'address'
            ]); // OPTIMIZATION: Only select needed columns

        // Filter by role if specified
        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // For admin dashboard - separate clients from admin/staff
        if ($request->has('dashboard_view')) {
            if ($request->dashboard_view === 'clients') {
                $query->where('role', 'client');
            } elseif ($request->dashboard_view === 'staff_admins') {
                $query->whereIn('role', ['admin', 'staff']);
            }
        }

        // Default: show all users for admin
        if (!$request->has('dashboard_view') && !$request->has('role')) {
            $query->whereIn('role', ['admin', 'staff', 'client']);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('username', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('first_name', 'like', '%' . $request->search . '%')
                  ->orWhere('last_name', 'like', '%' . $request->search . '%');
            });
        }

        $users = $query->where('id', '!=', $request->user()->id)
                      ->orderBy('created_at', 'desc')
                      ->paginate($request->get('per_page', 10));

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
            'success' => true
        ]);
    }    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users|max:255',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,staff,client',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        try {
            // Generate unique username from email
            $emailPart = explode('@', $request->email)[0];
            $username = $emailPart;
            $counter = 1;
            
            // Ensure username is unique
            while (User::where('username', $username)->exists()) {
                $username = $emailPart . $counter;
                $counter++;
            }
            
            $user = User::create([
                'username' => $username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'address' => $request->address,
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            return response()->json([
                'message' => 'User created successfully',
                'data' => $user,
                'success' => true
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create user',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function show(User $user)
    {
        return response()->json([
            'data' => $user,
            'success' => true
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'role' => 'required|in:admin,staff,client',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'password' => 'nullable|string|min:6',
        ]);

        try {
            $oldData = $user->only([
                'email', 'role', 'first_name', 
                'last_name', 'phone', 'address', 'is_active'
            ]);
            
            // Auto-generate username from email if not already set
            if (!$user->username && $request->has('email')) {
                $user->username = explode('@', $request->email)[0];
            }
            
            $updateData = [
                'email' => $request->email,
                'role' => $request->role,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone ?? $user->phone,
                'address' => $request->address ?? $user->address,
            ];
            
            // Only update is_active if explicitly provided
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }
            
            $user->update($updateData);

            if ($request->has('password') && $request->password) {
                $user->update(['password' => Hash::make($request->password)]);
            }

            // Log the action
            $changes = [];
            foreach ($oldData as $key => $value) {
                if ($value !== $user->{$key}) {
                    $changes[] = "{$key}: {$value} -> {$user->{$key}}";
                }
            }
            
            if (!empty($changes)) {
                \App\Models\ActionLog::log(
                    'update',
                    "Updated user {$user->first_name} {$user->last_name}: " . implode(', ', $changes),
                    'User',
                    $user->id
                );
            }

            return response()->json([
                'message' => 'User updated successfully',
                'data' => $user,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Cannot delete your own account',
                'success' => false
            ], 422);
        }

        // Only admins can delete/archive users
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'message' => 'Only admins can delete users',
                'success' => false
            ], 403);
        }

        try {
            // Soft delete (archive) instead of permanent delete
            $user->delete();

            // Log the action
            \App\Models\ActionLog::log(
                'archive',
                "Archived user: {$user->first_name} {$user->last_name} (Role: {$user->role})",
                'User',
                $user->id
            );

            return response()->json([
                'message' => 'User archived successfully',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to archive user',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function getArchived(Request $request)
    {
        // Only admins can view archived users
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Only admins can view archived users',
                'success' => false
            ], 403);
        }

        $query = User::onlyTrashed();

        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('username', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('first_name', 'like', '%' . $request->search . '%')
                  ->orWhere('last_name', 'like', '%' . $request->search . '%');
            });
        }

        $users = $query->orderBy('deleted_at', 'desc')
                      ->paginate($request->get('per_page', 10));

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
            'success' => true
        ]);
    }

    public function restore(Request $request, $id)
    {
        // Only admins can restore users
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Only admins can restore users',
                'success' => false
            ], 403);
        }

        try {
            $user = User::withTrashed()->findOrFail($id);
            $user->restore();
            // Set user as active when restoring
            $user->update(['is_active' => true]);

            return response()->json([
                'message' => 'User restored successfully',
                'data' => $user,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to restore user',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function permanentDelete(Request $request, $id)
    {
        // Only admins can permanently delete users
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Only admins can permanently delete users',
                'success' => false
            ], 403);
        }

        try {
            $user = User::withTrashed()->findOrFail($id);
            
            if ($user->id === auth()->id()) {
                return response()->json([
                    'message' => 'Cannot permanently delete your own account',
                    'success' => false
                ], 422);
            }

            $user->forceDelete();

            return response()->json([
                'message' => 'User permanently deleted',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to permanently delete user',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
        ]);

        try {
            $user->update($request->only(['first_name', 'last_name', 'phone', 'address']));

            return response()->json([
                'message' => 'Profile updated successfully',
                'data' => $user,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update profile',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    // NEW METHOD FOR TOGGLING USER STATUS
    public function toggleStatus(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Cannot deactivate your own account',
                'success' => false
            ], 422);
        }

        try {
            $oldStatus = $user->is_active;
            $user->update([
                'is_active' => !$user->is_active
            ]);

            // Log the action
            $action = $user->is_active ? 'Activated' : 'Deactivated';
            \App\Models\ActionLog::log(
                'toggle_status',
                "{$action} user: {$user->first_name} {$user->last_name} (Role: {$user->role})",
                'User',
                $user->id
            );

            return response()->json([
                'message' => $user->is_active ? 'User activated successfully' : 'User deactivated successfully',
                'data' => $user,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user status',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }
}