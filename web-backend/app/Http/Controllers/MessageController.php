<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Models\ActionLog;
use App\Models\MessageSettings;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // Optimized: Use a single query with subqueries to get conversations
            // instead of N+1 queries inside a map() loop
            
            // Step 1: Get all unique user IDs the current user has conversed with
            $sentTo = Message::where('sender_id', $user->id)->distinct()->pluck('receiver_id');
            $receivedFrom = Message::where('receiver_id', $user->id)->distinct()->pluck('sender_id');
            $userIds = $sentTo->merge($receivedFrom)->unique()->values();
            
            if ($userIds->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'success' => true
                ]);
            }
            
            // Step 2: Batch load all other users at once
            $otherUsers = User::whereIn('id', $userIds)
                ->get(['id', 'first_name', 'last_name', 'email', 'role', 'profile_picture'])
                ->keyBy('id');
            
            // Step 3: Get last message for each conversation using a single optimized query
            // Use a raw query with GROUP BY to get max message IDs in one query
            $lastMessageIds = \DB::table('messages')
                ->selectRaw('MAX(id) as last_msg_id')
                ->where(function($q) use ($user, $userIds) {
                    $q->where('sender_id', $user->id)->whereIn('receiver_id', $userIds);
                })
                ->orWhere(function($q) use ($user, $userIds) {
                    $q->where('receiver_id', $user->id)->whereIn('sender_id', $userIds);
                })
                ->groupByRaw("CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END", [$user->id])
                ->pluck('last_msg_id')
                ->toArray();
            
            // Batch load all last messages with their relationships
            $lastMessages = Message::with(['sender:id,first_name,last_name,email,role', 'receiver:id,first_name,last_name,email,role'])
                ->whereIn('id', $lastMessageIds)
                ->get()
                ->keyBy(function($msg) use ($user) {
                    return $msg->sender_id === $user->id ? $msg->receiver_id : $msg->sender_id;
                });
            
            // Step 4: Get unread counts in a single query
            $unreadCounts = Message::where('receiver_id', $user->id)
                ->where('read', false)
                ->whereIn('sender_id', $userIds)
                ->selectRaw('sender_id, COUNT(*) as unread_count')
                ->groupBy('sender_id')
                ->pluck('unread_count', 'sender_id');
            
            // Step 5: Build conversations array
            $conversations = $userIds->map(function($otherUserId) use ($otherUsers, $lastMessages, $unreadCounts) {
                return [
                    'user' => $otherUsers->get($otherUserId),
                    'last_message' => $lastMessages->get($otherUserId),
                    'unread_count' => $unreadCounts->get($otherUserId, 0),
                ];
            })->filter(function($conv) {
                return $conv['user'] !== null;
            })->values();

            return response()->json([
                'data' => $conversations,
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Message index error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load messages',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function show(Request $request, User $otherUser)
    {
        try {
            $user = $request->user();

            // Fetch messages between current user and the other user
            // Limit to latest 100 messages for performance, load more on demand
            $limit = $request->get('limit', 100);
            $before = $request->get('before'); // cursor-based: load messages before this ID

            $query = Message::where(function ($q) use ($user, $otherUser) {
                    $q->where('sender_id', $user->id)
                      ->where('receiver_id', $otherUser->id);
                })
                ->orWhere(function ($q) use ($user, $otherUser) {
                    $q->where('sender_id', $otherUser->id)
                      ->where('receiver_id', $user->id);
                })
                ->with(['sender:id,first_name,last_name,email,role', 'receiver:id,first_name,last_name,email,role']);

            if ($before) {
                $query->where('id', '<', $before);
            }

            $messages = $query->orderBy('id', 'desc')
                ->limit($limit + 1) // +1 to check if there are more
                ->get();

            $hasMore = $messages->count() > $limit;
            if ($hasMore) {
                $messages = $messages->slice(0, $limit);
            }

            // Reverse to chronological order for display
            $messages = $messages->reverse()->values();

            // Mark all unread messages FROM the other user TO the current user as read
            // This ensures the conversation shows as read when the user views it
            Message::where('sender_id', $otherUser->id)
                ->where('receiver_id', $user->id)
                ->where('read', false)
                ->update(['read' => true]);

            return response()->json([
                'data' => [
                    'messages' => $messages,
                    'other_user' => $otherUser,
                    'has_more' => $hasMore
                ],
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Message show error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load conversation',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
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

                // Check consecutive-message limit for clients (configurable by admin)
                // Users can only send N messages before the admin/staff replies
                // Each admin reply resets the counter
                $messageLimit = MessageSettings::getMessageLimit();
                $consecutiveCount = $this->getConsecutiveClientMessageCount($user->id, $receiver->id);

                if ($consecutiveCount >= $messageLimit) {
                    return response()->json([
                        'message' => "You can only send up to {$messageLimit} messages at a time. Please wait for the admin to reply before sending more.",
                        'success' => false,
                        'error_code' => 'MESSAGE_LIMIT_EXCEEDED',
                        'remaining_messages' => 0,
                        'message_limit' => $messageLimit
                    ], 429);
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

            // Send email ONLY for appointment-related messages
            if (($request->user()->isAdmin() || $request->user()->isStaff()) && $request->type === 'appointment') {
                try {
                    \Illuminate\Support\Facades\Mail::to($receiver->email)->queue(new \App\Mail\AdminMessageMail(
                        $receiver,
                        $request->subject ?? 'Appointment Message',
                        $request->message,
                        $request->type
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
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
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
        try {
            $user = $request->user();
            $otherUser = User::findOrFail($userId);

            // Fetch messages with pagination for performance
            $limit = $request->get('limit', 100);
            $before = $request->get('before');

            $query = Message::where(function($q) use ($user, $otherUser) {
                    $q->where('sender_id', $user->id)
                      ->where('receiver_id', $otherUser->id);
                })
                ->orWhere(function($q) use ($user, $otherUser) {
                    $q->where('sender_id', $otherUser->id)
                      ->where('receiver_id', $user->id);
                })
                ->with(['sender:id,first_name,last_name,email,role', 'receiver:id,first_name,last_name,email,role']);

            if ($before) {
                $query->where('id', '<', $before);
            }

            $messages = $query->orderBy('id', 'desc')
                ->limit($limit + 1)
                ->get();

            $hasMore = $messages->count() > $limit;
            if ($hasMore) {
                $messages = $messages->slice(0, $limit);
            }

            // Reverse to chronological order
            $messages = $messages->reverse()->values();

            // Mark all unread messages FROM the other user TO current user as read
            Message::where('sender_id', $otherUser->id)
                ->where('receiver_id', $user->id)
                ->where('read', false)
                ->update(['read' => true]);

            return response()->json([
                'data' => [
                    'messages' => $messages,
                    'other_user' => $otherUser,
                    'has_more' => $hasMore
                ],
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Conversation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load conversation',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    // Check if user can message another user
    public function canMessage(Request $request, $userId)
    {
        $user = $request->user();
        $otherUser = User::findOrFail($userId);

        // If user is not a client, they can always message
        if (!$user->isClient()) {
            return response()->json([
                'can_message' => true,
                'reason' => 'Admin/Staff can always message',
                'remaining_messages' => null
            ]);
        }

        // Clients can always message admin/staff, but with a configurable message limit
        $messageLimit = MessageSettings::getMessageLimit();
        $consecutiveCount = $this->getConsecutiveClientMessageCount($user->id, $otherUser->id);
        $remaining = max(0, $messageLimit - $consecutiveCount);

        return response()->json([
            'can_message' => $remaining > 0,
            'remaining_messages' => $remaining,
            'message_limit' => $messageLimit,
            'reason' => $remaining > 0 
                ? "You can send {$remaining} more message(s) before the admin replies."
                : "You have reached the {$messageLimit}-message limit. Please wait for the admin to reply before sending more."
        ]);
    }

    // Get admin contacts for users (even without existing conversations)
    public function getAdminContacts(Request $request)
    {
        try {
            $user = $request->user();

            // Return only one admin contact for users to message
            // Prefer the admin who has most recently messaged this user, otherwise the first admin
            $lastAdminWhoMessaged = Message::where('receiver_id', $user->id)
                ->whereHas('sender', function($q) {
                    $q->whereIn('role', ['admin', 'staff']);
                })
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastAdminWhoMessaged) {
                $admin = User::where('id', $lastAdminWhoMessaged->sender_id)
                    ->where('id', '!=', $user->id)
                    ->first(['id', 'first_name', 'last_name', 'email', 'role', 'profile_picture']);
                
                if ($admin) {
                    return response()->json([
                        'data' => [$admin],
                        'success' => true
                    ]);
                }
            }

            // Fallback: get the first active admin account
            $admin = User::where('role', 'admin')
                ->where('id', '!=', $user->id)
                ->where('is_active', true)
                ->orderBy('id', 'asc')
                ->first(['id', 'first_name', 'last_name', 'email', 'role', 'profile_picture']);

            return response()->json([
                'data' => $admin ? [$admin] : [],
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Get admin contacts error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load admin contacts',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    // Get the message limit status for a conversation
    public function getMessageLimitStatus(Request $request, $userId)
    {
        try {
            $user = $request->user();

            // Admin/staff have no limit
            if (!$user->isClient()) {
                return response()->json([
                    'has_limit' => false,
                    'remaining_messages' => null,
                    'message_limit' => null,
                    'success' => true
                ]);
            }

            $messageLimit = MessageSettings::getMessageLimit();
            $consecutiveCount = $this->getConsecutiveClientMessageCount($user->id, $userId);
            $remaining = max(0, $messageLimit - $consecutiveCount);

            return response()->json([
                'has_limit' => true,
                'remaining_messages' => $remaining,
                'message_limit' => $messageLimit,
                'consecutive_sent' => $consecutiveCount,
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Get message limit status error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get message limit status',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    /**
     * Count consecutive messages sent by a client to a receiver (admin/staff)
     * without a reply from the receiver in between.
     * Each reply from the receiver resets the counter.
     */
    private function getConsecutiveClientMessageCount($clientId, $receiverId)
    {
        // Get the most recent messages between these two users, ordered by newest first
        $recentMessages = Message::where(function($q) use ($clientId, $receiverId) {
                $q->where('sender_id', $clientId)->where('receiver_id', $receiverId);
            })
            ->orWhere(function($q) use ($clientId, $receiverId) {
                $q->where('sender_id', $receiverId)->where('receiver_id', $clientId);
            })
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        // Count consecutive messages from the client (starting from most recent)
        $count = 0;
        foreach ($recentMessages as $msg) {
            if ($msg->sender_id == $clientId) {
                $count++;
            } else {
                // Found a message from the admin/staff - stop counting
                break;
            }
        }

        return $count;
    }

    // NEW: Get all messages for dashboard (flat list, not grouped)
    public function getAllMessages(Request $request)
    {
        try {
            $user = $request->user();

            $messages = Message::where(function($q) use ($user) {
                    $q->where('sender_id', $user->id)
                      ->orWhere('receiver_id', $user->id);
                })
                ->with(['sender:id,first_name,last_name,email,role', 'receiver:id,first_name,last_name,email,role'])
                ->orderBy('created_at', 'desc')
                ->limit(200)
                ->get();

            return response()->json([
                'data' => $messages,
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('getAllMessages error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'message' => 'Failed to load messages',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
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
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }
}