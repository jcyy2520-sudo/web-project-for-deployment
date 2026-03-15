<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use App\Models\ActionLog;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Create cache key based on query parameters
        $cacheKey = 'users_index_' . md5(json_encode($request->all()));
        
        // Cache the result for 15 seconds for responsive data
        $result = Cache::remember($cacheKey, 15, function () use ($request) {
            $query = User::query()
                ->select([
                    'id', 'username', 'email', 'first_name', 'last_name', 
                    'phone', 'role', 'is_active', 'account_status', 'account_status_reason', 'created_at', 'updated_at', 'address'
                ]);

            if ($request->has('role') && $request->role !== 'all') {
                $query->where('role', $request->role);
            }

            // Filter by account_status if specified
            if ($request->has('account_status') && $request->account_status !== 'all') {
                $query->where('account_status', $request->account_status);
            }

            if ($request->has('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('username', 'like', '%' . $request->search . '%')
                      ->orWhere('email', 'like', '%' . $request->search . '%')
                      ->orWhere('first_name', 'like', '%' . $request->search . '%')
                      ->orWhere('last_name', 'like', '%' . $request->search . '%');
                });
            }

            // Allow including self in results (e.g., for admin listing)
            if (!$request->boolean('include_self', false)) {
                $query->where('id', '!=', $request->user()->id);
            }

            $query->orderBy('created_at', 'desc');

            // Support both limit and pagination
            $limit = $request->get('limit', null);
            $perPage = $request->get('per_page', 10);

            if ($limit) {
                // Return all results up to limit (no pagination)
                $users = $query->limit($limit)->get();

                return [
                    'data' => $users,
                    'success' => true
                ];
            }

            $users = $query->paginate($perPage);

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
        // Cache for 15 seconds to reduce DB load while keeping data relatively fresh
        // Include cache version so clearUsersCache() can invalidate all entries
        $cacheVersion = Cache::get('users_cache_version', 0);
        $cacheKey = 'users_by_role_' . md5(json_encode($request->all()) . '_' . $request->user()->id . '_v' . $cacheVersion);
        
        $result = Cache::remember($cacheKey, 15, function () use ($request) {
            $query = User::query()
                ->select([
                    'id', 'username', 'email', 'first_name', 'last_name', 
                    'phone', 'role', 'is_active', 'account_status', 'account_status_reason', 'created_at', 'updated_at', 'address'
                ]);

            // Filter by role if specified
            if ($request->has('role') && $request->role !== 'all') {
                $query->where('role', $request->role);
            }

            // Filter by account_status if specified
            if ($request->has('account_status') && $request->account_status !== 'all') {
                $query->where('account_status', $request->account_status);
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

            // Allow including self in results (e.g., for admin listing)
            if (!$request->boolean('include_self', false)) {
                $query = $query->where('id', '!=', $request->user()->id);
            }

            $query = $query->orderBy('created_at', 'desc');

            // Support both limit and pagination
            $limit = $request->get('limit', null);
            $perPage = $request->get('per_page', 10);

            if ($limit) {
                // Return all results up to limit (no pagination)
                $users = $query->limit($limit)->get();

                return [
                    'data' => $users,
                    'success' => true
                ];
            }

            // Standard pagination
            $users = $query->paginate($perPage);

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
    }    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users|max:255',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,staff,cashier,client',
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
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'address' => $request->address,
                'profile_completed' => true,
                'verification_method' => 'admin',
            ]);

            // Set sensitive fields explicitly (not via mass assignment — excluded from $fillable for security)
            $user->password = Hash::make($request->password);
            $user->is_active = true;
            $user->email_verified_at = now();
            $user->role = $request->role;
            $user->save();

            // Clear users cache so new user appears immediately
            $this->clearUsersCache();

            ActionLog::log('create', "Created user: {$user->first_name} {$user->last_name} ({$user->email}, role: {$user->role})", 'User', $user->id);

            return response()->json([
                'message' => 'User created successfully',
                'data' => $user,
                'success' => true
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create user',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
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
            'role' => 'required|in:admin,staff,cashier,client',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'password' => 'nullable|string|min:8',
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
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone ?? $user->phone,
                'address' => $request->address ?? $user->address,
            ];
            
            $user->update($updateData);

            // Set protected fields explicitly (excluded from $fillable for security)
            $user->role = $request->role;
            if ($request->has('is_active')) {
                $user->is_active = $request->is_active;
            }

            if ($request->has('password') && $request->password) {
                $user->password = Hash::make($request->password);
            }
            
            $user->save();

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

            // Clear users cache so changes appear immediately
            $this->clearUsersCache();

            return response()->json([
                'message' => 'User updated successfully',
                'data' => $user,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
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

            // Clear users cache so changes appear immediately
            $this->clearUsersCache();

            return response()->json([
                'message' => 'User archived successfully',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to archive user',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
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
            // Set user as active when restoring (set explicitly for security)
            $user->is_active = true;
            $user->save();

            // Clear users cache so changes appear immediately
            $this->clearUsersCache();

            return response()->json([
                'message' => 'User restored successfully',
                'data' => $user,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to restore user',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
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

            // Clear users cache so changes appear immediately
            $this->clearUsersCache();

            return response()->json([
                'message' => 'User permanently deleted',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to permanently delete user',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
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

            ActionLog::log('update_profile', "Updated profile information", 'User', $user->id);

            return response()->json([
                'message' => 'Profile updated successfully',
                'data' => $user,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update profile',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
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
            $user->is_active = !$user->is_active;
            $user->account_status = $user->is_active ? 'active' : 'deactivated';
            $user->account_status_reason = $user->is_active ? null : ($request->input('reason', 'Deactivated by administrator'));
            $user->save();

            // Log the action
            $action = $user->is_active ? 'Activated' : 'Deactivated';
            \App\Models\ActionLog::log(
                'toggle_status',
                "{$action} user: {$user->first_name} {$user->last_name} (Role: {$user->role})",
                'User',
                $user->id
            );

            // If user was reactivated/unblocked, send email and in-app notification
            if ($user->is_active && !$oldStatus) {
                // Send reactivation email
                try {
                    \Illuminate\Support\Facades\Mail::to($user->email)->queue(
                        new \App\Mail\AccountReactivatedMail(
                            $user->first_name . ' ' . $user->last_name,
                            $user->email,
                            $request->input('reason', '')
                        )
                    );
                } catch (\Exception $e) {
                    \Log::warning('Failed to send account reactivation email: ' . $e->getMessage());
                }

                // Create in-app notification
                try {
                    \App\Models\Notification::create([
                        'user_id' => $user->id,
                        'title' => 'Account Reactivated',
                        'message' => 'Your account has been reactivated by an administrator. You now have full access to the system.',
                        'type' => 'account',
                        'data' => [
                            'action' => 'reactivated',
                            'admin_name' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
                        ],
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Failed to create reactivation notification: ' . $e->getMessage());
                }
            }

            // If user was deactivated/blocked, send email and in-app notification
            if (!$user->is_active && $oldStatus) {
                // Send deactivation email
                try {
                    \Illuminate\Support\Facades\Mail::to($user->email)->queue(
                        new \App\Mail\AccountActionMail(
                            $user->first_name . ' ' . $user->last_name,
                            $user->email,
                            'deactivated',
                            $request->input('reason', 'Your account has been deactivated by an administrator.'),
                            config('app.frontend_url', url('/')) . '/contact'
                        )
                    );
                } catch (\Exception $e) {
                    \Log::warning('Failed to send account deactivation email: ' . $e->getMessage());
                }

                // Create in-app notification
                try {
                    \App\Models\Notification::create([
                        'user_id' => $user->id,
                        'title' => 'Account Deactivated',
                        'message' => 'Your account has been deactivated by an administrator. Please contact support if you believe this was an error.',
                        'type' => 'account',
                        'data' => [
                            'action' => 'deactivated',
                            'admin_name' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
                        ],
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Failed to create deactivation notification: ' . $e->getMessage());
                }
            }

            // Clear users cache so changes appear immediately
            $this->clearUsersCache();

            return response()->json([
                'message' => $user->is_active ? 'User activated successfully' : 'User deactivated successfully',
                'data' => $user,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user status',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    /**
     * Unblock a blocked user account.
     */
    public function unblockUser(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Cannot unblock your own account',
                'success' => false
            ], 422);
        }

        if ($user->account_status !== 'blocked') {
            return response()->json([
                'message' => 'This user is not blocked.',
                'success' => false
            ], 422);
        }

        try {
            // Set explicitly — is_active and account_status are excluded from $fillable
            $user->is_active = true;
            $user->account_status = 'active';
            $user->account_status_reason = null;
            $user->save();

            // Log the action
            \App\Models\ActionLog::log(
                'unblock_user',
                "Unblocked user: {$user->first_name} {$user->last_name} (Role: {$user->role})",
                'User',
                $user->id
            );

            // Send email notification
            try {
                \Illuminate\Support\Facades\Mail::to($user->email)->queue(
                    new \App\Mail\AccountReactivatedMail(
                        $user->first_name . ' ' . $user->last_name,
                        $user->email,
                        'Your account has been unblocked by an administrator. You can now log in and use the system.'
                    )
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to send unblock email: ' . $e->getMessage());
            }

            // Create in-app notification
            try {
                \App\Models\Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Account Unblocked',
                    'message' => 'Your account has been unblocked by an administrator. You now have full access to the system.',
                    'type' => 'account',
                    'data' => [
                        'action' => 'unblocked',
                        'admin_name' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
                    ],
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to create unblock notification: ' . $e->getMessage());
            }

            $this->clearUsersCache();

            return response()->json([
                'message' => 'User unblocked successfully.',
                'data' => $user,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to unblock user.',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    /**
     * Get blocked users with pagination and search.
     */
    public function blockedUsers(Request $request)
    {
        $cacheVersion = Cache::get('users_cache_version', 0);
        $cacheKey = 'users_blocked_' . md5(json_encode($request->all()) . '_v' . $cacheVersion);
        
        $result = Cache::remember($cacheKey, 15, function () use ($request) {
            $query = User::query()
                ->select([
                    'id', 'username', 'email', 'first_name', 'last_name', 
                    'phone', 'role', 'is_active', 'account_status', 'account_status_reason', 'created_at', 'updated_at', 'address'
                ])
                ->where('account_status', 'blocked');

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%");
                });
            }

            if ($request->has('role') && $request->role !== 'all') {
                $query->where('role', $request->role);
            }

            $perPage = $request->get('per_page', 10);
            $users = $query->orderBy('updated_at', 'desc')->paginate($perPage);

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

    /**
     * Clear all users-related cache entries to ensure real-time data.
     */
    private function clearUsersCache()
    {
        try {
            // Use a tracked list of cache keys instead of flushing everything
            $prefixes = ['users_index_', 'users_by_role_', 'users_blocked_'];
            $commonParams = [
                '[]',
                json_encode(['role' => 'client']),
                json_encode(['role' => 'admin']),
                json_encode(['role' => 'staff']),
                json_encode(['role' => 'all']),
                json_encode(['dashboard_view' => 'clients']),
                json_encode(['dashboard_view' => 'staff_admins']),
            ];

            foreach ($prefixes as $prefix) {
                foreach ($commonParams as $param) {
                    Cache::forget($prefix . md5($param));
                }
            }

            // Also clear with limit parameter variants commonly used by admin dashboard
            $limitVariants = [
                json_encode(['role' => 'client', 'limit' => '1000', 'include_self' => 'true']),
                json_encode(['role' => 'client', 'limit' => '1000', 'include_self' => '1']),
                json_encode(['role' => 'admin', 'limit' => '1000', 'include_self' => 'true']),
                json_encode(['role' => 'admin', 'limit' => '1000', 'include_self' => '1']),
                json_encode(['limit' => '1000', 'include_self' => 'true']),
                json_encode(['limit' => '1000', 'include_self' => '1']),
            ];

            foreach ($prefixes as $prefix) {
                foreach ($limitVariants as $param) {
                    Cache::forget($prefix . md5($param));
                }
            }
        } catch (\Exception $e) {
            // Silently fail - cache clearing is best-effort
            \Log::warning('Failed to clear users cache: ' . $e->getMessage());
        }
    }
}