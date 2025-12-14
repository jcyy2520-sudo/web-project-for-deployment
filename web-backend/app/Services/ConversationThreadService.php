<?php

namespace App\Services;

use App\Models\ChatbotConversation;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * ConversationThreadService - Multi-threaded conversations
 * 
 * Handles:
 * - Multiple parallel conversations per user
 * - Smart context switching
 * - Conversation suggestions
 * - Proactive notifications
 * - Thread organization and tagging
 */
class ConversationThreadService
{
    private const CONVERSATION_THREADS_KEY = 'conv_threads:user:';
    private const ACTIVE_CONVERSATION_KEY = 'active_conversation:user:';
    private const MAX_PARALLEL_CONVERSATIONS = 10;

    /**
     * Create a new conversation thread
     */
    public function createThread(int $userId, string $topic = null, array $metadata = []): array
    {
        try {
            $conversationId = Str::uuid()->toString();
            $sessionId = Str::uuid()->toString();

            $thread = [
                'conversation_id' => $conversationId,
                'session_id' => $sessionId,
                'user_id' => $userId,
                'topic' => $topic,
                'title' => $topic ? $this->generateThreadTitle($topic, $metadata) : 'Untitled Conversation',
                'created_at' => now()->toDateTimeString(),
                'last_activity_at' => now()->toDateTimeString(),
                'message_count' => 0,
                'metadata' => $metadata,
                'is_active' => true,
                'status' => 'active',
            ];

            // Store in database
            ChatbotConversation::create([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'session_id' => $sessionId,
                'title' => $thread['title'],
                'status' => 'active',
                'context_data' => $metadata,
            ]);

            // Cache thread metadata
            $this->cacheThread($userId, $thread);

            Log::info("Created conversation thread: {$conversationId} for user {$userId}");

            return [
                'success' => true,
                'thread' => $thread,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create conversation thread: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get all threads for a user
     */
    public function getUserThreads(int $userId, $limit = 20): array
    {
        try {
            $conversations = ChatbotConversation::where('user_id', $userId)
                ->where('status', 'active')
                ->orderBy('last_activity_at', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();

            return [
                'success' => true,
                'threads' => $conversations,
                'count' => count($conversations),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get user threads: ' . $e->getMessage());
            return [
                'success' => false,
                'threads' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Switch active conversation
     */
    public function switchThread(int $userId, string $conversationId): array
    {
        try {
            // Verify user owns this conversation
            $conversation = ChatbotConversation::where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->first();

            if (!$conversation) {
                return [
                    'success' => false,
                    'error' => 'Conversation not found or not owned by user',
                ];
            }

            // Update active conversation in cache
            $cacheKey = self::ACTIVE_CONVERSATION_KEY . $userId;
            Cache::put($cacheKey, $conversationId, 86400); // 24 hours

            return [
                'success' => true,
                'conversation_id' => $conversationId,
                'switched_at' => now()->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to switch thread: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get active conversation for user
     */
    public function getActiveThread(int $userId): ?string
    {
        try {
            $cacheKey = self::ACTIVE_CONVERSATION_KEY . $userId;
            $activeConversationId = Cache::get($cacheKey);

            if (!$activeConversationId) {
                // Return most recent conversation
                $recent = ChatbotConversation::where('user_id', $userId)
                    ->where('status', 'active')
                    ->orderBy('last_activity_at', 'desc')
                    ->first();

                $activeConversationId = $recent?->conversation_id;
            }

            return $activeConversationId;
        } catch (\Exception $e) {
            Log::error('Failed to get active thread: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Archive a conversation thread
     */
    public function archiveThread(int $userId, string $conversationId): array
    {
        try {
            $conversation = ChatbotConversation::where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->first();

            if (!$conversation) {
                return [
                    'success' => false,
                    'error' => 'Conversation not found',
                ];
            }

            $conversation->update(['status' => 'archived']);

            Log::info("Archived conversation: {$conversationId}");

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Failed to archive thread: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get suggested next topics based on conversation history
     */
    public function getSuggestions(int $userId, string $conversationId): array
    {
        try {
            $suggestions = [];

            // Get recent messages
            $recentMessages = ChatMessage::where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            if ($recentMessages->isEmpty()) {
                return $this->getDefaultSuggestions($userId);
            }

            // Analyze conversation for topics
            $topics = $this->extractTopics($recentMessages);

            // Generate contextual suggestions
            $suggestions = $this->generateSuggestions($topics, $userId);

            return [
                'success' => true,
                'suggestions' => $suggestions,
                'topics_detected' => $topics,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get suggestions: ' . $e->getMessage());
            return [
                'success' => true,
                'suggestions' => $this->getDefaultSuggestions($userId),
            ];
        }
    }

    /**
     * Send proactive notification
     */
    public function sendProactiveNotification(int $userId, array $notification): array
    {
        try {
            $notificationData = [
                'id' => Str::uuid()->toString(),
                'user_id' => $userId,
                'title' => $notification['title'],
                'message' => $notification['message'],
                'type' => $notification['type'] ?? 'info',
                'action' => $notification['action'] ?? null,
                'sent_at' => now()->toDateTimeString(),
                'read' => false,
            ];

            // Store notification
            Cache::push("notifications:user:{$userId}", $notificationData);

            Log::info("Sent proactive notification to user {$userId}");

            return [
                'success' => true,
                'notification' => $notificationData,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send notification: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get pending notifications for user
     */
    public function getPendingNotifications(int $userId, $limit = 10): array
    {
        try {
            $notifications = Cache::get("notifications:user:{$userId}", []);
            $unread = array_slice($notifications, 0, $limit);

            return [
                'success' => true,
                'notifications' => $unread,
                'count' => count($unread),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get notifications: ' . $e->getMessage());
            return [
                'success' => false,
                'notifications' => [],
            ];
        }
    }

    /**
     * Tag/label a conversation
     */
    public function tagThread(int $userId, string $conversationId, array $tags): array
    {
        try {
            $conversation = ChatbotConversation::where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->first();

            if (!$conversation) {
                return ['success' => false, 'error' => 'Conversation not found'];
            }

            $contextData = $conversation->context_data ?? [];
            $contextData['tags'] = $tags;
            $conversation->update(['context_data' => $contextData]);

            return ['success' => true, 'tags' => $tags];
        } catch (\Exception $e) {
            Log::error('Failed to tag thread: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search conversations
     */
    public function searchThreads(int $userId, string $query): array
    {
        try {
            $results = ChatbotConversation::where('user_id', $userId)
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                        ->orWhere('summary', 'like', "%{$query}%");
                })
                ->orderBy('last_activity_at', 'desc')
                ->limit(10)
                ->get()
                ->toArray();

            return [
                'success' => true,
                'results' => $results,
                'count' => count($results),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to search threads: ' . $e->getMessage());
            return [
                'success' => false,
                'results' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Private helper methods
     */

    private function generateThreadTitle(string $topic, array $metadata): string
    {
        $timestamp = now()->format('M d, H:i');
        return "{$topic} - {$timestamp}";
    }

    private function cacheThread(int $userId, array $thread): void
    {
        $cacheKey = self::CONVERSATION_THREADS_KEY . $userId;
        $threads = Cache::get($cacheKey, []);
        $threads[$thread['conversation_id']] = $thread;
        Cache::put($cacheKey, $threads, 604800); // 1 week
    }

    private function extractTopics(mixed $messages): array
    {
        $topics = [];
        foreach ($messages as $message) {
            // Basic topic extraction
            if (preg_match('/appointment|booking/i', $message->message)) {
                $topics[] = 'appointments';
            }
            if (preg_match('/payment|refund/i', $message->message)) {
                $topics[] = 'payments';
            }
            if (preg_match('/schedule|time|date/i', $message->message)) {
                $topics[] = 'scheduling';
            }
        }
        return array_unique($topics);
    }

    private function generateSuggestions(array $topics, int $userId): array
    {
        $suggestions = [];

        if (in_array('appointments', $topics)) {
            $suggestions[] = 'View my upcoming appointments';
            $suggestions[] = 'Cancel an appointment';
            $suggestions[] = 'Book a new appointment';
        }

        if (in_array('payments', $topics)) {
            $suggestions[] = 'Check payment status';
            $suggestions[] = 'Request a refund';
            $suggestions[] = 'View payment history';
        }

        if (empty($suggestions)) {
            return $this->getDefaultSuggestions($userId);
        }

        return array_slice($suggestions, 0, 5);
    }

    private function getDefaultSuggestions(int $userId): array
    {
        return [
            'View my appointments',
            'Book a new appointment',
            'Check my account',
            'Get help with payment',
            'Contact support',
        ];
    }
}
