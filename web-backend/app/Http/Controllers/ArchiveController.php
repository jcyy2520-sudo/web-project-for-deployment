<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Models\Appointment;
use App\Models\ActionLog;

class ArchiveController extends Controller
{
    public function index()
    {
        try {
            // PERFORMANCE OPTIMIZATION: Use pagination instead of loading all data
            // This prevents memory issues with large archives
            
            $perPage = 20;
            
            // Get soft deleted users with pagination
            $deletedUsers = User::onlyTrashed()
                ->select('id', 'first_name', 'last_name', 'email', 'role', 'deleted_at')
                ->paginate($perPage, ['*'], 'users_page');
            
            // Get soft deleted appointments with pagination and only necessary columns
            $deletedAppointments = Appointment::onlyTrashed()
                ->with(['user' => function($query) {
                    $query->withTrashed()->select('id', 'first_name', 'last_name', 'email');
                }])
                ->select('id', 'user_id', 'type', 'status', 'appointment_date', 'deleted_at')
                ->paginate($perPage, ['*'], 'appointments_page');
            
            // Format data for response
            $formattedUsers = $deletedUsers->getCollection()->map(function($user) {
                return [
                    'id' => $user->id,
                    'type' => 'user',
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'deleted_at' => $user->deleted_at,
                ];
            });
            
            $formattedAppointments = $deletedAppointments->getCollection()->map(function($appointment) {
                return [
                    'id' => $appointment->id,
                    'type' => 'appointment',
                    'name' => $appointment->user 
                        ? $appointment->user->first_name . ' ' . $appointment->user->last_name . ' - ' . $appointment->type 
                        : 'Unknown User - ' . $appointment->type,
                    'email' => $appointment->user ? $appointment->user->email : 'N/A',
                    'role' => 'appointment',
                    'deleted_at' => $appointment->deleted_at,
                ];
            });
            
            // Get counts using database aggregation instead of loading all records
            $counts = DB::select('
                SELECT
                    (SELECT COUNT(*) FROM users WHERE deleted_at IS NOT NULL) as users,
                    (SELECT COUNT(*) FROM appointments WHERE deleted_at IS NOT NULL) as appointments
            ');
            
            $countData = $counts[0] ?? (object)['users' => 0, 'appointments' => 0];

            return response()->json([
                'success' => true,
                'data' => $formattedUsers->merge($formattedAppointments)->values(),
                'counts' => [
                    'users' => $countData->users,
                    'appointments' => $countData->appointments,
                    'total' => $countData->users + $countData->appointments
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Failed to load archived items: ' . $e->getMessage() : 'Failed to load archived items'
            ], 500);
        }
    }

    public function restore(Request $request)
    {
        $request->validate([
            'item_id' => 'required|integer',
            'item_type' => 'required|in:user,appointment'
        ]);

        try {
            if ($request->item_type === 'user') {
                $user = User::onlyTrashed()->find($request->item_id);
                if ($user) {
                    $user->restore();
                    
                    // Reactivate the user upon restore (set explicitly for security)
                    $user->is_active = true;
                    $user->account_status = 'active';
                    $user->account_status_reason = null;
                    $user->save();
                    
                    // Clear all users-related caches
                    try {
                        Cache::forget('users_index_' . md5(json_encode(['role' => 'client', 'limit' => '1000'])));
                        Cache::forget('users_index_' . md5(json_encode(['per_page' => '1000'])));
                        Cache::forget('users_index_' . md5(json_encode(['role' => 'client'])));
                        Cache::forget('users_index_' . md5(json_encode(['role' => 'admin'])));
                        Cache::forget('users_archived_list');
                        Cache::forget('deactivated_accounts');
                    } catch (\Exception $e) {
                        \Log::warning('Failed to clear users cache on restore: ' . $e->getMessage());
                    }

                    ActionLog::log('restore', "Restored archived user: {$user->first_name} {$user->last_name} (ID: {$user->id})", 'User', $user->id);

                    return response()->json([
                        'success' => true,
                        'message' => 'User restored successfully'
                    ]);
                }
            } elseif ($request->item_type === 'appointment') {
                $appointment = Appointment::onlyTrashed()->find($request->item_id);
                if ($appointment) {
                    $appointment->restore();
                    
                    // Clear appointments-related caches
                    Cache::forget('public_init_data');

                    ActionLog::log('restore', "Restored archived appointment (ID: {$appointment->id})", 'Appointment', $appointment->id);

                    return response()->json([
                        'success' => true,
                        'message' => 'Appointment restored successfully'
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Item not found in archive'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Failed to restore item: ' . $e->getMessage() : 'Failed to restore item'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $request = request();
        $request->validate([
            'item_type' => 'required|in:user,appointment'
        ]);

        if ($request->item_type === 'user') {
            $user = User::onlyTrashed()->find($id);
            if ($user) {
                $userName = $user->first_name . ' ' . $user->last_name;
                $user->forceDelete();

                ActionLog::log('permanent_delete', "Permanently deleted user: {$userName} (ID: {$id})", 'User', $id);

                return response()->json([
                    'success' => true,
                    'message' => 'User permanently deleted'
                ]);
            }
        } elseif ($request->item_type === 'appointment') {
            $appointment = Appointment::onlyTrashed()->find($id);
            if ($appointment) {
                $appointment->forceDelete();

                ActionLog::log('permanent_delete', "Permanently deleted appointment (ID: {$id})", 'Appointment', $id);

                return response()->json([
                    'success' => true,
                    'message' => 'Appointment permanently deleted'
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Item not found in archive'
        ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Failed to permanently delete item: ' . $e->getMessage() : 'Failed to permanently delete item'
            ], 500);
        }
    }
}