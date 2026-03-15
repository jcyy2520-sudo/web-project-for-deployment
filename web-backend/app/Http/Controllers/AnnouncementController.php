<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\ActionLog;

class AnnouncementController extends Controller
{
    /**
     * List all announcements (admin)
     */
    public function index(Request $request)
    {
        $query = Announcement::with('creator:id,first_name,last_name,email')
            ->orderBy('created_at', 'desc');

        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $announcements = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'announcements' => $announcements,
            'success' => true,
        ]);
    }

    /**
     * Create and publish a new announcement (admin)
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|min:5',
            'priority' => 'required|in:low,normal,high,urgent',
            'target_audience' => 'required|in:all_users,clients,staff',
        ]);

        $announcement = Announcement::create([
            'created_by' => Auth::id(),
            'title' => $request->title,
            'message' => $request->message,
            'priority' => $request->priority,
            'target_audience' => $request->target_audience,
            'is_active' => true,
            'published_at' => now(),
        ]);

        // Send notification to target users
        $targetQuery = User::where('is_active', true);

        switch ($request->target_audience) {
            case 'clients':
                $targetQuery->where('role', 'client');
                break;
            case 'staff':
                $targetQuery->whereIn('role', ['staff', 'cashier']);
                break;
            case 'all_users':
            default:
                $targetQuery->where('role', '!=', 'admin');
                break;
        }

        $targetUsers = $targetQuery->get();
        $count = 0;

        foreach ($targetUsers as $user) {
            Notification::create([
                'user_id' => $user->id,
                'title' => $announcement->title,
                'message' => $announcement->message,
                'type' => 'announcement',
                'data' => [
                    'announcement_id' => $announcement->id,
                    'priority' => $announcement->priority,
                    'created_by' => Auth::user()->first_name . ' ' . Auth::user()->last_name,
                ],
            ]);
            $count++;
        }

        Log::info("Announcement created and sent to {$count} users", [
            'announcement_id' => $announcement->id,
            'title' => $announcement->title,
        ]);

        ActionLog::log('create', "Created announcement: {$announcement->title} (sent to {$count} users)", 'Announcement', $announcement->id);

        return response()->json([
            'message' => "Announcement published and sent to {$count} users.",
            'announcement' => $announcement->load('creator:id,first_name,last_name,email'),
            'recipients_count' => $count,
            'success' => true,
        ], 201);
    }

    /**
     * View a single announcement (admin)
     */
    public function show(Announcement $announcement)
    {
        return response()->json([
            'announcement' => $announcement->load('creator:id,first_name,last_name,email'),
            'success' => true,
        ]);
    }

    /**
     * Update an announcement (admin)
     */
    public function update(Request $request, Announcement $announcement)
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'message' => 'sometimes|string|min:5',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'is_active' => 'sometimes|boolean',
        ]);

        $announcement->update($request->only(['title', 'message', 'priority', 'is_active']));

        ActionLog::log('update', "Updated announcement: {$announcement->title}", 'Announcement', $announcement->id);

        return response()->json([
            'message' => 'Announcement updated successfully.',
            'announcement' => $announcement->load('creator:id,first_name,last_name,email'),
            'success' => true,
        ]);
    }

    /**
     * Delete an announcement (admin)
     */
    public function destroy(Announcement $announcement)
    {
        $title = $announcement->title;
        $announcementId = $announcement->id;
        $announcement->delete();

        ActionLog::log('delete', "Deleted announcement: {$title} (ID: {$announcementId})", 'Announcement', $announcementId);

        return response()->json([
            'message' => 'Announcement deleted successfully.',
            'success' => true,
        ]);
    }
}
