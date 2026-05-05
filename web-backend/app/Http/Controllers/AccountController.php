<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AccountAppeal;
use App\Models\ActionLog;
use App\Mail\AccountDeletedMail;
use App\Mail\AccountActionMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

class AccountController extends Controller
{
    /**
     * User self-deletes their own account.
     * Requires typing "confirm" and password verification.
     */
    public function selfDelete(Request $request)
    {
        $request->validate([
            'confirmation' => 'required|string|in:confirm',
            'password' => 'nullable|string',
        ]);

        $user = $request->user();

        // Prevent admin from self-deleting
        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Admin accounts cannot be self-deleted.',
                'success' => false
            ], 403);
        }

        // Verify password only if provided
        if ($request->filled('password') && !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Incorrect password.',
                'success' => false
            ], 422);
        }

        try {
            $userName = $user->first_name . ' ' . $user->last_name;
            $userEmail = $user->email;

            // Log the action before deletion
            ActionLog::log(
                'account_self_delete',
                "User {$userName} ({$userEmail}) deleted their own account",
                'User',
                $user->id
            );

            // Clean up profile picture file if it exists
            if ($user->profile_picture && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_picture)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_picture);
            }

            // Revoke all tokens
            $user->tokens()->delete();

            // Force delete the user (permanent)
            $user->forceDelete();

            // Send confirmation email (synchronous to ensure delivery before response)
            try {
                Mail::to($userEmail)->send(new AccountDeletedMail($userName, $userEmail));
            } catch (\Exception $e) {
                \Log::warning('Failed to send account deletion email: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Your account has been permanently deleted.',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete account.',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    /**
     * Admin performs action on a user account (delete/block/deactivate) with a reason.
     * Creates an appeal record and sends email with appeal link.
     */
    public function adminAction(Request $request, User $user)
    {
        $request->validate([
            'action' => 'required|in:deleted,blocked,deactivated',
            'reason' => 'required|string|min:10|max:1000',
        ]);

        $admin = $request->user();

        if (!$admin->isAdmin()) {
            return response()->json([
                'message' => 'Only admins can perform this action.',
                'success' => false
            ], 403);
        }

        if ($user->id === $admin->id) {
            return response()->json([
                'message' => 'Cannot perform this action on your own account.',
                'success' => false
            ], 422);
        }

        try {
            $actionType = $request->action;
            $reason = $request->reason;
            $userName = $user->first_name . ' ' . $user->last_name;
            $userEmail = $user->email;

            // Create appeal record while keeping admin-only fields out of mass assignment.
            $appeal = new AccountAppeal([
                'token' => AccountAppeal::generateToken(),
                'user_id' => $user->id,
                'user_email' => $userEmail,
                'user_name' => $userName,
                'action_type' => $actionType,
                'action_reason' => $reason,
            ]);
            $appeal->acted_by = $admin->id;
            $appeal->status = 'pending';
            $appeal->save();

            // Perform the action (set fields explicitly — not mass-assigned for security)
            switch ($actionType) {
                case 'deleted':
                    $user->is_active = false;
                    $user->account_status = 'deleted';
                    $user->account_status_reason = $reason;
                    $user->save();
                    $user->tokens()->delete();
                    $user->delete(); // Soft delete
                    break;
                case 'blocked':
                    $user->is_active = false;
                    $user->account_status = 'blocked';
                    $user->account_status_reason = $reason;
                    $user->save();
                    $user->tokens()->delete();
                    break;
                case 'deactivated':
                    $user->is_active = false;
                    $user->account_status = 'deactivated';
                    $user->account_status_reason = $reason;
                    $user->save();
                    $user->tokens()->delete();
                    break;
            }

            // Log the action
            ActionLog::log(
                'account_admin_action',
                "Admin {$admin->first_name} {$admin->last_name} {$actionType} user {$userName} ({$userEmail}). Reason: {$reason}",
                'User',
                $user->id
            );

            // Build appeal URL (frontend route)
            $frontendUrl = config('app.frontend_url', config('app.url', 'http://localhost:5173'));
            $appealUrl = $frontendUrl . '/appeal/' . $appeal->token;

            // Send email with reason and appeal link
            try {
                Mail::to($userEmail)->queue(new AccountActionMail(
                    $userName,
                    $userEmail,
                    $actionType,
                    $reason,
                    $appealUrl
                ));
            } catch (\Exception $e) {
                \Log::warning('Failed to send account action email: ' . $e->getMessage());
            }

            // Clear users cache
            $this->clearUsersCache();

            $actionLabels = [
                'deleted' => 'archived',
                'blocked' => 'blocked',
                'deactivated' => 'deactivated',
            ];

            return response()->json([
                'message' => "User has been {$actionLabels[$actionType]} successfully. An email with appeal instructions has been sent.",
                'data' => [
                    'appeal_id' => $appeal->id,
                    'action_type' => $actionType,
                ],
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to perform account action.',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    /**
     * Clear users-related cache entries.
     */
    private function clearUsersCache()
    {
        try {
            // Increment cache version to invalidate all users_by_role_ caches
            $currentVersion = Cache::get('users_cache_version', 0);
            Cache::put('users_cache_version', $currentVersion + 1, 3600);
            
            // Also clear known specific cache keys
            $prefixes = ['users_index_', 'users_blocked_'];
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
        } catch (\Exception $e) {
            \Log::warning('Failed to clear users cache: ' . $e->getMessage());
        }
    }
}
