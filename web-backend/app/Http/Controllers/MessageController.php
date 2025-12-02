<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Models\ActionLog;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get all users the current user has messaged with
            $userIds = Message::where(function($query) use ($user) {
                    $query->where('sender_id', $user->id)
                          ->orWhere('receiver_id', $user->id);
                })
                ->get(['sender_id', 'receiver_id'])
                ->map(function($msg) use ($user) {
                    return $msg->sender_id === $user->id ? $msg->receiver_id : $msg->sender_id;
                })
                ->unique()
                ->values();

            // Build conversations array - one per user
            $conversations = $userIds->map(function($otherUserId) use ($user) {
                // Get the most recent message with this user
                $lastMessage = Message::where(function($query) use ($user, $otherUserId) {
                        $query->where('sender_id', $user->id)
                              ->where('receiver_id', $otherUserId);
                    })
                    ->orWhere(function($query) use ($user, $otherUserId) {
                        $query->where('sender_id', $otherUserId)
                              ->where('receiver_id', $user->id);
                    })
                    ->latest()
                    ->with(['sender', 'receiver'])
                    ->first();

                // Get the other user
                $otherUser = User::find($otherUserId);

                // Count unread messages from this user
                $unreadCount = Message::where('sender_id', $otherUserId)
                    ->where('receiver_id', $user->id)
                    ->where('read', false)
                    ->count();

                return [
                    'user' => $otherUser,
                    'last_message' => $lastMessage,
                    'unread_count' => $unreadCount,
                ];
            })->values();

            return response()->json([
                'data' => $conversations,
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Message index error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load messages',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function show(Request $request, User $otherUser)
    {
        try {
            $user = $request->user();

            $messages = Message::where(function ($query) use ($user, $otherUser) {
                    $query->where('sender_id', $user->id)
                          ->where('receiver_id', $otherUser->id);
                })
                ->orWhere(function ($query) use ($user, $otherUser) {
                    $query->where('sender_id', $otherUser->id)
                          ->where('receiver_id', $user->id);
                })
                ->with(['sender', 'receiver'])
                ->orderBy('created_at', 'asc')
                ->get();

            // Mark messages as read
            Message::where('sender_id', $otherUser->id)
                ->where('receiver_id', $user->id)
                ->where('read', false)
                ->update(['read' => true]);

            return response()->json([
                'data' => [
                    'messages' => $messages,
                    'other_user' => $otherUser
                ],
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Message show error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load conversation',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'receiver_id' => 'required|exists:users,id',
                'message' => 'required|string|max:1000',
                'reply_to_message_id' => 'nullable|exists:messages,id',
                'subject' => 'nullable|string|max:255',
                'type' => 'nullable|string|max:50'
            ]);

            $user = $request->user();
            $receiver = User::findOrFail($request->receiver_id);

            // If user is a client, they can only message admin/staff
            // Note: Admin/staff can always message anyone
            if ($user->isClient()) {
                // Check if receiver is admin or staff
                if (!$receiver->isAdmin() && !$receiver->isStaff()) {
                    return response()->json([
                        'message' => 'You can only message admins or staff',
                        'success' => false
                    ], 403);
                }

                // Check 3-reply limit for clients on specific parent messages
                if ($request->reply_to_message_id) {
                    $parentMessage = Message::findOrFail($request->reply_to_message_id);
                    
                    // Verify the parent message is from the receiver (admin/staff)
                    if ($parentMessage->sender_id !== $receiver->id) {
                        return response()->json([
                            'message' => 'Invalid message to reply to',
                            'success' => false
                        ], 400);
                    }

                    // Count replies from this user to this parent message
                    $replyCount = Message::where('reply_to_message_id', $parentMessage->id)
                        ->where('sender_id', $user->id)
                        ->count();

                    if ($replyCount >= 3) {
                        return response()->json([
                            'message' => 'You have reached the 3-reply limit for this message. Wait for the admin to send another message to continue.',
                            'success' => false,
                            'error_code' => 'REPLY_LIMIT_EXCEEDED'
                        ], 429);
                    }
                }
            }

            $message = Message::create([
                'sender_id' => $request->user()->id,
                'receiver_id' => $request->receiver_id,
                'message' => $request->message,
                'subject' => $request->subject ?? null,
                'type' => $request->type ?? null,
                'reply_to_message_id' => $request->reply_to_message_id ?? null
            ]);

            // Send email if sender is admin/staff and receiver is client
            if ($request->user()->isAdmin() || $request->user()->isStaff()) {
                try {
                    \Illuminate\Support\Facades\Mail::to($receiver->email)->send(new \App\Mail\AdminMessageMail(
                        $receiver,
                        $request->subject ?? 'New Message',
                        $request->message,
                        $request->type ?? 'general'
                    ));
                } catch (\Exception $e) {
                    \Log::error('Failed to send message email: ' . $e->getMessage());
                    // Don't fail the API request if email fails
                }
            }

            // Log the message action with full message content
            ActionLog::log(
                'message',
                "Sent message to {$receiver->first_name} {$receiver->last_name}. Message content: {$request->message}",
                'Message',
                $message->id
            );

            return response()->json([
                'message' => 'Message sent successfully',
                'data' => $message->load(['sender', 'receiver']),
                'success' => true
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'success' => false
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Message store error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to send message',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function getUsers(Request $request)
    {
        $user = $request->user();
        $role = $request->role;

        $query = User::where('id', '!=', $user->id);

        if ($role) {
            $query->where('role', $role);
        }

        if ($user->isAdmin()) {
            // Admin can message staff and clients
            $query->whereIn('role', ['staff', 'client']);
        } elseif ($user->isStaff()) {
            // Staff can message admin and clients
            $query->whereIn('role', ['admin', 'client']);
        } elseif ($user->isClient()) {
            // Clients can message staff and admin
            $query->whereIn('role', ['admin', 'staff']);
        }

        $users = $query->get(['id', 'username', 'email', 'first_name', 'last_name', 'role']);

        return response()->json([
            'data' => $users,
            'success' => true
        ]);
    }

    // NEW METHODS FOR USER DASHBOARD

    public function getStaff(Request $request)
    {
        $staff = User::whereIn('role', ['staff', 'admin'])
            ->where('id', '!=', $request->user()->id)
            ->get(['id', 'first_name', 'last_name', 'role', 'email']);

        return response()->json([
            'data' => $staff,
            'success' => true
        ]);
    }

    public function conversation(Request $request, $userId)
    {
        $user = $request->user();
        $otherUser = User::findOrFail($userId);

        $messages = Message::where(function($query) use ($user, $otherUser) {
                $query->where('sender_id', $user->id)
                      ->where('receiver_id', $otherUser->id);
            })
            ->orWhere(function($query) use ($user, $otherUser) {
                $query->where('sender_id', $otherUser->id)
                      ->where('receiver_id', $user->id);
            })
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark messages as read
        Message::where('sender_id', $otherUser->id)
            ->where('receiver_id', $user->id)
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json([
            'data' => [
                'messages' => $messages,
                'other_user' => $otherUser
            ],
            'success' => true
        ]);
    }

    // NEW: Check if user can message another user
    public function canMessage(Request $request, $userId)
    {
        $user = $request->user();
        $otherUser = User::findOrFail($userId);

        // If user is not a client, they can always message
        if (!$user->isClient()) {
            return response()->json([
                'can_message' => true,
                'reason' => 'Admin/Staff can always message'
            ]);
        }

        // If user is a client, check if the other user has messaged them first
        $hasConversation = Message::where(function($query) use ($user, $otherUser) {
            $query->where('sender_id', $otherUser->id)
                  ->where('receiver_id', $user->id);
        })->exists();

        return response()->json([
            'can_message' => $hasConversation,
            'reason' => $hasConversation ? 'You have received a message from this user' : 'You can only message after receiving a message from the admin or staff'
        ]);
    }

    // NEW: Get all messages for dashboard (flat list, not grouped)
    public function getAllMessages(Request $request)
    {
        $user = $request->user();

        $messages = Message::where('sender_id', $user->id)
            ->orWhere('receiver_id', $user->id)
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $messages,
            'success' => true
        ]);
    }

    // NEW: Delete entire conversation with a user
    public function deleteConversation(Request $request, $userId)
    {
        try {
            $user = $request->user();
            $otherUser = User::findOrFail($userId);

            // Delete all messages between the two users
            $deletedCount = Message::where(function($query) use ($user, $otherUser) {
                    $query->where('sender_id', $user->id)
                          ->where('receiver_id', $otherUser->id);
                })
                ->orWhere(function($query) use ($user, $otherUser) {
                    $query->where('sender_id', $otherUser->id)
                          ->where('receiver_id', $user->id);
                })
                ->delete();

            // Log the deletion action
            ActionLog::log(
                'delete',
                "Deleted conversation with {$otherUser->first_name} {$otherUser->last_name} ({$deletedCount} messages)",
                'Message',
                null
            );

            return response()->json([
                'message' => 'Conversation deleted successfully',
                'deleted_count' => $deletedCount,
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Delete conversation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete conversation',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }
}