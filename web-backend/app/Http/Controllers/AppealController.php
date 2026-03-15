<?php

namespace App\Http\Controllers;

use App\Models\AccountAppeal;
use App\Models\User;
use App\Models\ActionLog;
use App\Mail\AppealResolvedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class AppealController extends Controller
{
    /**
     * PUBLIC: Verify an appeal token and return appeal info.
     * No authentication required (user's account may be deleted/blocked).
     */
    public function verify(string $token)
    {
        $appeal = AccountAppeal::where('token', $token)->first();

        if (!$appeal) {
            return response()->json([
                'message' => 'Invalid or expired appeal link.',
                'success' => false
            ], 404);
        }

        // If already submitted, return that info
        if ($appeal->isSubmitted()) {
            return response()->json([
                'message' => 'This appeal has already been submitted.',
                'data' => [
                    'already_submitted' => true,
                    'status' => $appeal->status,
                    'user_name' => $appeal->user_name,
                    'action_type' => $appeal->action_type,
                    'submitted_at' => $appeal->appeal_submitted_at,
                ],
                'success' => true
            ]);
        }

        return response()->json([
            'data' => [
                'already_submitted' => false,
                'user_name' => $appeal->user_name,
                'user_email' => $appeal->user_email,
                'action_type' => $appeal->action_type,
                'action_reason' => $appeal->action_reason,
                'categories' => AccountAppeal::appealCategories(),
            ],
            'success' => true
        ]);
    }

    /**
     * PUBLIC: Submit an appeal (no auth needed).
     */
    public function submit(Request $request, string $token)
    {
        $request->validate([
            'appeal_category' => 'required|string',
            'appeal_message' => 'required|string|min:20|max:2000',
        ]);

        $appeal = AccountAppeal::where('token', $token)->first();

        if (!$appeal) {
            return response()->json([
                'message' => 'Invalid or expired appeal link.',
                'success' => false
            ], 404);
        }

        if ($appeal->isSubmitted()) {
            return response()->json([
                'message' => 'This appeal has already been submitted.',
                'success' => false
            ], 422);
        }

        try {
            $appeal->update([
                'appeal_category' => $request->appeal_category,
                'appeal_message' => $request->appeal_message,
                'appeal_submitted_at' => now(),
            ]);

            return response()->json([
                'message' => 'Your appeal has been submitted successfully. You will be notified by email once it has been reviewed.',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to submit appeal.',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    /**
     * ADMIN: List all appeals with filtering.
     */
    public function index(Request $request)
    {
        $query = AccountAppeal::with(['actedByAdmin:id,first_name,last_name', 'resolvedByAdmin:id,first_name,last_name'])
            ->whereNotNull('appeal_submitted_at'); // Only show submitted appeals

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by action type
        if ($request->has('action_type') && $request->action_type !== 'all') {
            $query->where('action_type', $request->action_type);
        }

        // Search by user name or email
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('user_email', 'like', "%{$search}%");
            });
        }

        $appeals = $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                         ->orderBy('appeal_submitted_at', 'desc')
                         ->paginate($request->get('per_page', 10));

        return response()->json([
            'data' => $appeals->items(),
            'meta' => [
                'current_page' => $appeals->currentPage(),
                'last_page' => $appeals->lastPage(),
                'per_page' => $appeals->perPage(),
                'total' => $appeals->total(),
            ],
            'stats' => [
                'pending' => AccountAppeal::submitted()->pending()->count(),
                'total' => AccountAppeal::submitted()->count(),
            ],
            'success' => true
        ]);
    }

    /**
     * ADMIN: Resolve an appeal (approve or reject).
     */
    public function resolve(Request $request, AccountAppeal $appeal)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_response' => 'nullable|string|min:10|max:1000',
        ]);

        if (!$appeal->isSubmitted()) {
            return response()->json([
                'message' => 'This appeal has not been submitted yet.',
                'success' => false
            ], 422);
        }

        if ($appeal->status !== 'pending') {
            return response()->json([
                'message' => 'This appeal has already been resolved.',
                'success' => false
            ], 422);
        }

        $admin = $request->user();

        try {
            $appeal->status = $request->status;
            $appeal->admin_response = $request->admin_response;
            $appeal->resolved_by = $admin->id;
            $appeal->resolved_at = now();
            $appeal->save();

            // If approved, restore the user account
            if ($request->status === 'approved') {
                $user = User::withTrashed()->find($appeal->user_id);
                if ($user) {
                    // Restore if soft-deleted
                    if ($user->trashed()) {
                        $user->restore();
                    }
                    // Reactivate and reset account status (set explicitly, not mass-assigned)
                    $user->is_active = true;
                    $user->account_status = 'active';
                    $user->account_status_reason = null;
                    $user->save();

                    // Clear users cache so admin dashboard reflects changes
                    $this->clearUsersCache();

                    // Create in-app notification for the reactivated user
                    try {
                        \App\Models\Notification::create([
                            'user_id' => $user->id,
                            'title' => 'Account Reactivated — Appeal Approved',
                            'message' => 'Your appeal has been approved and your account has been reactivated. You now have full access to the system.' . ($request->admin_response ? ' Admin note: ' . $request->admin_response : ''),
                            'type' => 'account',
                            'data' => [
                                'action' => 'appeal_approved',
                                'appeal_id' => $appeal->id,
                                'admin_name' => $admin->first_name . ' ' . $admin->last_name,
                            ],
                        ]);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to create appeal approved notification: ' . $e->getMessage());
                    }
                }
            }

            // Log the action
            ActionLog::log(
                'appeal_resolved',
                "Admin {$admin->first_name} {$admin->last_name} {$request->status} appeal from {$appeal->user_name} ({$appeal->user_email})",
                'AccountAppeal',
                $appeal->id
            );

            // Send email notification to user
            try {
                $appeal->refresh();
                Mail::to($appeal->user_email)->queue(new AppealResolvedMail($appeal));
            } catch (\Exception $e) {
                \Log::warning('Failed to send appeal resolved email: ' . $e->getMessage());
            }

            return response()->json([
                'message' => "Appeal has been {$request->status} successfully.",
                'data' => $appeal->fresh()->load(['actedByAdmin:id,first_name,last_name', 'resolvedByAdmin:id,first_name,last_name']),
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to resolve appeal.',
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
        } catch (\Exception $e) {
            \Log::warning('Failed to clear users cache: ' . $e->getMessage());
        }
    }
}
