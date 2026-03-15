<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->get('per_page', 20);
        $isRead = $request->get('is_read');

        $query = $user->notifications()->latest();

        if ($isRead !== null) {
            $query->where('is_read', $isRead);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
            'unread_count' => $user->notifications()->where('is_read', false)->count()
        ]);
    }

    public function unread(Request $request)
    {
        $user = Auth::user();
        $notifications = $user->notifications()
            ->where('is_read', false)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'count' => $notifications->count()
        ]);
    }

    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        $this->authorize('view', $notification);

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->notifications()
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }

    public function markAsUnread($id)
    {
        $notification = Notification::findOrFail($id);
        $this->authorize('view', $notification);

        $notification->markAsUnread();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as unread'
        ]);
    }

    public function delete($id)
    {
        $notification = Notification::findOrFail($id);
        $this->authorize('delete', $notification);

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted'
        ]);
    }

    public function deleteAll()
    {
        $user = Auth::user();
        $user->notifications()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All notifications deleted'
        ]);
    }

    public function batchDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $user = Auth::user();
        $deleted = $user->notifications()->whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted} notifications deleted",
            'deleted_count' => $deleted,
        ]);
    }

    public function clearRead()
    {
        $user = Auth::user();
        $deleted = $user->notifications()->where('is_read', true)->delete();

        return response()->json([
            'success' => true,
            'message' => "$deleted read notifications cleared",
            'deleted_count' => $deleted,
        ]);
    }

    public function unreadCount()
    {
        $user = Auth::user();
        $count = $user->notifications()->where('is_read', false)->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }

    public function getPreferences()
    {
        $user = Auth::user();
        $preferences = $user->notificationPreferences()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'email_notifications' => true,
                'email_appointment_approved' => true,
                'email_appointment_declined' => true,
                'email_appointment_reminder' => true,
                'email_new_message' => true,
                'email_status_change' => true,
                'in_app_notifications' => true,
                'in_app_appointment_updates' => true,
                'in_app_messages' => true,
                'in_app_reminders' => true
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $preferences
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $user = Auth::user();
        $preferences = $user->notificationPreferences()->firstOrCreate(['user_id' => $user->id]);

        $validated = $request->validate([
            'email_notifications' => 'nullable|boolean',
            'email_appointment_approved' => 'nullable|boolean',
            'email_appointment_declined' => 'nullable|boolean',
            'email_appointment_reminder' => 'nullable|boolean',
            'email_new_message' => 'nullable|boolean',
            'email_status_change' => 'nullable|boolean',
            'in_app_notifications' => 'nullable|boolean',
            'in_app_appointment_updates' => 'nullable|boolean',
            'in_app_messages' => 'nullable|boolean',
            'in_app_reminders' => 'nullable|boolean',
            'quiet_hours' => 'nullable|array',
            'quiet_hours.enabled' => 'nullable|boolean',
            'quiet_hours.start' => 'nullable|date_format:H:i',
            'quiet_hours.end' => 'nullable|date_format:H:i'
        ]);

        $preferences->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'data' => $preferences
        ]);
    }
}
