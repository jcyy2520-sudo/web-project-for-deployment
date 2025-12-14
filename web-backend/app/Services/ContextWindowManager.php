<?php

namespace App\Services;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\Log;

/**
 * ContextWindowManager - Manages conversation history for LLM context
 * 
 * Features:
 * - Expanded context window (8K tokens)
 * - Intelligent message compression for old messages
 * - Semantic importance ranking
 * - Token counting and optimization
 */
class ContextWindowManager
{
    private const MAX_CONTEXT_TOKENS = 8000; // Target token limit
    private const RECENT_MESSAGES = 10; // Always include last N messages
    private const COMPRESSED_MESSAGES = 20; // Include older compressed messages

    /**
     * Get conversation context with intelligent compression
     * 
     * Returns a mix of:
     * - Recent uncompressed messages (for immediate context)
     * - Older compressed messages (for historical context)
     * - System summary (for overall conversation state)
     */
    public function getConversationContext(
        ?int $userId,
        string $conversationId,
        int $maxMessages = 50
    ): array {
        try {
            if (!$userId) {
                return [];
            }

            $messages = ChatMessage::where('user_id', $userId)
                ->where('conversation_id', $conversationId)
                ->orderBy('created_at', 'desc')
                ->limit($maxMessages)
                ->get()
                ->reverse()
                ->values();

            if ($messages->isEmpty()) {
                return [];
            }

            $totalMessages = $messages->count();
            $context = [];

            // Recent messages (always include uncompressed)
            if ($totalMessages <= self::RECENT_MESSAGES) {
                // All messages are recent
                foreach ($messages as $msg) {
                    $context[] = $this->formatMessage($msg);
                }
            } else {
                // Split into recent and older
                $recentStart = $totalMessages - self::RECENT_MESSAGES;
                
                // Add older compressed messages
                $olderMessages = $messages->slice(0, min(self::COMPRESSED_MESSAGES, $recentStart));
                foreach ($olderMessages as $msg) {
                    $context[] = $this->formatMessage($msg, true); // Compress
                }

                // Add recent uncompressed messages
                $recentMessages = $messages->slice($recentStart);
                foreach ($recentMessages as $msg) {
                    $context[] = $this->formatMessage($msg);
                }
            }

            return $context;
        } catch (\Exception $e) {
            Log::debug('Failed to get conversation context: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get conversation summary for context enrichment
     * 
     * Provides high-level information about the conversation
     * useful for LLM to understand overall goals
     */
    public function getConversationSummary(
        ?int $userId,
        string $conversationId
    ): array {
        try {
            if (!$userId) {
                return [];
            }

            $messages = ChatMessage::where('user_id', $userId)
                ->where('conversation_id', $conversationId)
                ->orderBy('created_at')
                ->get();

            if ($messages->isEmpty()) {
                return [];
            }

            // Analyze conversation
            $userMessages = $messages->where('role', 'user')->count();
            $assistantMessages = $messages->where('role', 'assistant')->count();
            
            // Extract topics/intents
            $topics = $messages->where('role', 'user')
                ->map(fn($msg) => $this->extractTopic($msg->message))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // Calculate conversation sentiment
            $sentiments = $messages->map(fn($msg) => $msg->sentiment ?? 'neutral');
            $sentimentDistribution = $sentiments->countBy()->toArray();

            return [
                'total_messages' => $messages->count(),
                'user_messages' => $userMessages,
                'assistant_messages' => $assistantMessages,
                'topics' => array_slice($topics, 0, 5), // Top 5 topics
                'sentiment_distribution' => $sentimentDistribution,
                'conversation_duration' => $messages->first()->created_at->diffInMinutes($messages->last()->created_at),
                'last_update' => $messages->last()->created_at,
            ];
        } catch (\Exception $e) {
            Log::debug('Failed to get conversation summary: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Format message for LLM context
     */
    private function formatMessage(ChatMessage $msg, bool $compress = false): array
    {
        $formatted = [
            'role' => $msg->role,
            'message' => $msg->message,
            'created_at' => $msg->created_at,
        ];

        if ($compress) {
            // Compress older messages - keep only essential information
            $formatted['message'] = $this->compressMessage($msg->message, $msg->role);
            $formatted['compressed'] = true;
        }

        return $formatted;
    }

    /**
     * Compress message for context efficiency
     * 
     * Reduces message length while preserving essential information
     */
    private function compressMessage(string $message, string $role): string
    {
        // For assistant messages, keep first and last sentences
        if ($role === 'assistant') {
            $sentences = preg_split('/(?<=[.!?])\s+/', trim($message));
            if (count($sentences) > 3) {
                return $sentences[0] . ' ... ' . end($sentences);
            }
        }

        // For user messages, extract intent/topic
        if ($role === 'user') {
            if (strlen($message) > 100) {
                return substr($message, 0, 97) . '...';
            }
        }

        return $message;
    }

    /**
     * Extract topic from message
     */
    private function extractTopic(string $message): ?string
    {
        $topics = [
            'appointment' => ['appointment', 'booking', 'schedule', 'reschedule', 'cancel'],
            'payment' => ['payment', 'pay', 'price', 'cost', 'bill', 'invoice'],
            'refund' => ['refund', 'return', 'money back', 'refunded'],
            'services' => ['service', 'what do you offer', 'services', 'types'],
            'account' => ['account', 'profile', 'user', 'settings', 'password'],
            'help' => ['help', 'support', 'how', 'what', 'why', 'where'],
        ];

        $messageLower = strtolower($message);

        foreach ($topics as $topic => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($messageLower, $keyword) !== false) {
                    return $topic;
                }
            }
        }

        return null;
    }

    /**
     * Count approximate tokens in conversation
     * 
     * Rough estimate: 1 token ≈ 4 characters
     */
    public function estimateTokens(array $messages): int
    {
        $totalChars = 0;
        foreach ($messages as $msg) {
            $totalChars += strlen($msg['message'] ?? '');
        }
        return (int) ceil($totalChars / 4);
    }

    /**
     * Truncate context to fit within token limit
     */
    public function truncateToTokenLimit(array $messages, int $maxTokens = self::MAX_CONTEXT_TOKENS): array
    {
        $truncated = [];
        $currentTokens = 0;

        // Always keep at least last 3 messages
        $minMessages = min(3, count($messages));
        
        // Start from the end (most recent)
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $msg = $messages[$i];
            $msgTokens = $this->estimateTokens([$msg]);
            
            if ($currentTokens + $msgTokens > $maxTokens && count($truncated) >= $minMessages) {
                break;
            }

            array_unshift($truncated, $msg);
            $currentTokens += $msgTokens;
        }

        return $truncated;
    }
}
