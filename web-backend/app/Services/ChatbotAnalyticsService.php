<?php

namespace App\Services;

use App\Models\ChatbotAnalytics;
use App\Models\ChatbotConversation;
use App\Models\ChatbotRateLimit;
use Illuminate\Support\Facades\Log;

/**
 * ChatbotAnalyticsService
 * 
 * Handles logging, tracking, and analytics for the chatbot system.
 * Tracks: usage patterns, failed requests, sentiment, performance metrics.
 */
class ChatbotAnalyticsService
{
    private LanguageDetectionService $languageService;

    public function __construct(LanguageDetectionService $languageService)
    {
        $this->languageService = $languageService;
    }

    /**
     * Log a chatbot interaction
     */
    public function logInteraction(array $data): ChatbotAnalytics
    {
        $startTime = $data['start_time'] ?? microtime(true);
        $endTime = microtime(true);
        $responseTimeMs = (int)(($endTime - $startTime) * 1000);

        // Detect language
        $languageInfo = $this->languageService->detect($data['user_message'] ?? '');
        
        // Check for offensive content
        $offensiveCheck = $this->languageService->containsOffensiveContent($data['user_message'] ?? '');

        // Determine if this is a priority message
        $isPriority = $this->determinePriority($data);
        $priorityReason = $isPriority ? $this->getPriorityReason($data) : null;

        $analytics = ChatbotAnalytics::create([
            'user_id' => $data['user_id'] ?? null,
            'conversation_id' => $data['conversation_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'user_message' => $data['user_message'] ?? null,
            'bot_response' => $data['bot_response'] ?? null,
            'detected_intent' => $data['intent'] ?? null,
            'detected_language' => $languageInfo['language'],
            'entities_extracted' => $data['entities'] ?? null,
            'sentiment' => $data['sentiment'] ?? 'neutral',
            'sentiment_score' => $data['sentiment_score'] ?? 0,
            'is_priority' => $isPriority,
            'priority_reason' => $priorityReason,
            'response_time_ms' => $responseTimeMs,
            'response_source' => $data['response_source'] ?? 'pattern',
            'confidence_score' => $data['confidence'] ?? null,
            'was_successful' => $data['success'] ?? true,
            'failure_reason' => $data['failure_reason'] ?? null,
            'is_out_of_scope' => $data['out_of_scope'] ?? false,
            'action_type' => $data['action_type'] ?? null,
            'action_executed' => $data['action_executed'] ?? false,
            'action_result' => $data['action_result'] ?? null,
            'contains_profanity' => $offensiveCheck['contains_offensive'],
            'is_spam' => $data['is_spam'] ?? false,
            'is_rate_limited' => $data['is_rate_limited'] ?? false,
            'user_role' => $data['user_role'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
        ]);

        // Update conversation record
        if (!empty($data['conversation_id'])) {
            $this->updateConversation($data['conversation_id'], $data, $analytics);
        }

        return $analytics;
    }

    /**
     * Update conversation tracking
     */
    private function updateConversation(string $conversationId, array $data, ChatbotAnalytics $analytics): void
    {
        $conversation = ChatbotConversation::getOrCreate(
            $conversationId,
            $data['user_id'] ?? null,
            $data['session_id'] ?? null
        );

        $conversation->recordMessage('user', $data['user_message'] ?? '', [
            'sentiment' => $data['sentiment'] ?? 'neutral',
            'sentiment_score' => $data['sentiment_score'] ?? 0,
            'detected_language' => $analytics->detected_language,
            'detected_intent' => $data['intent'] ?? null,
            'is_priority' => $analytics->is_priority,
        ]);
    }

    /**
     * Determine if a message should be flagged as priority
     */
    private function determinePriority(array $data): bool
    {
        // High negative sentiment
        if (($data['sentiment_score'] ?? 0) >= 4) {
            return true;
        }

        // Frustrated or angry sentiment
        if (in_array($data['sentiment'] ?? '', ['angry', 'frustrated'])) {
            return true;
        }

        // Contains urgency keywords
        $message = mb_strtolower($data['user_message'] ?? '');
        $urgencyKeywords = [
            'urgent', 'emergency', 'immediately', 'asap', 'critical',
            'help me', 'please help', 'need help now',
            'importante', 'ayuda', 'kailangan ko', 'tulong', 'urgent po',
        ];

        foreach ($urgencyKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }

        // Failed multiple times in conversation
        if (!empty($data['conversation_id'])) {
            $recentFailures = ChatbotAnalytics::where('conversation_id', $data['conversation_id'])
                ->where('was_successful', false)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->count();

            if ($recentFailures >= 3) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the reason for priority flagging
     */
    private function getPriorityReason(array $data): string
    {
        $reasons = [];

        if (($data['sentiment_score'] ?? 0) >= 4) {
            $reasons[] = 'high_negative_sentiment';
        }

        if (in_array($data['sentiment'] ?? '', ['angry', 'frustrated'])) {
            $reasons[] = 'user_frustrated';
        }

        $message = mb_strtolower($data['user_message'] ?? '');
        if (preg_match('/(urgent|emergency|critical|asap|immediately)/i', $message)) {
            $reasons[] = 'urgency_detected';
        }

        if (preg_match('/(help me|please help|need help|tulong|ayuda)/i', $message)) {
            $reasons[] = 'help_request';
        }

        return implode(', ', $reasons) ?: 'unknown';
    }

    /**
     * Get analytics summary for dashboard
     */
    public function getDashboardSummary(string $period = 'day', ?int $userId = null): array
    {
        $summary = ChatbotAnalytics::getSummary($period, $userId);
        
        // Add conversation metrics
        $conversationQuery = ChatbotConversation::query();
        
        switch ($period) {
            case 'hour':
                $conversationQuery->where('created_at', '>=', now()->subHour());
                break;
            case 'day':
                $conversationQuery->where('created_at', '>=', now()->startOfDay());
                break;
            case 'week':
                $conversationQuery->where('created_at', '>=', now()->subWeek());
                break;
            case 'month':
                $conversationQuery->where('created_at', '>=', now()->subMonth());
                break;
        }

        $summary['conversations'] = [
            'total' => (clone $conversationQuery)->count(),
            'active' => (clone $conversationQuery)->where('status', 'active')->count(),
            'rate_limited' => (clone $conversationQuery)->where('was_rate_limited', true)->count(),
            'needs_attention' => (clone $conversationQuery)->where('requires_human_follow_up', true)->count(),
        ];

        // Add rate limit metrics
        $rateLimitQuery = ChatbotRateLimit::query();
        switch ($period) {
            case 'hour':
                $rateLimitQuery->where('created_at', '>=', now()->subHour());
                break;
            case 'day':
                $rateLimitQuery->where('created_at', '>=', now()->startOfDay());
                break;
            case 'week':
                $rateLimitQuery->where('created_at', '>=', now()->subWeek());
                break;
            case 'month':
                $rateLimitQuery->where('created_at', '>=', now()->subMonth());
                break;
        }

        $summary['rate_limiting'] = [
            'total_blocks' => (clone $rateLimitQuery)->where('is_blocked', true)->count(),
            'spam_blocks' => (clone $rateLimitQuery)->where('block_reason', 'spam_detection')->count(),
        ];

        return $summary;
    }

    /**
     * Get messages needing human attention
     */
    public function getPriorityMessages(int $limit = 50): array
    {
        $messages = ChatbotAnalytics::where('is_priority', true)
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return [
            'total' => ChatbotAnalytics::where('is_priority', true)->count(),
            'messages' => $messages,
        ];
    }

    /**
     * Get conversations needing human follow-up
     */
    public function getConversationsNeedingAttention(): array
    {
        $conversations = ChatbotConversation::getNeedingAttention();

        return [
            'total' => $conversations->count(),
            'conversations' => $conversations,
        ];
    }

    /**
     * Get failed/out-of-scope questions for training
     */
    public function getTrainingData(int $limit = 100): array
    {
        $failed = ChatbotAnalytics::where(function($q) {
            $q->where('was_successful', false)
              ->orWhere('is_out_of_scope', true);
        })
            ->whereNotNull('user_message')
            ->select('user_message', 'detected_intent', 'failure_reason', 'is_out_of_scope', 'created_at')
            ->distinct('user_message')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return [
            'total_unique' => $failed->count(),
            'questions' => $failed,
        ];
    }

    /**
     * Get response time analytics
     */
    public function getPerformanceMetrics(string $period = 'day'): array
    {
        $query = ChatbotAnalytics::query()->whereNotNull('response_time_ms');
        
        switch ($period) {
            case 'hour':
                $query->where('created_at', '>=', now()->subHour());
                break;
            case 'day':
                $query->where('created_at', '>=', now()->startOfDay());
                break;
            case 'week':
                $query->where('created_at', '>=', now()->subWeek());
                break;
        }

        return [
            'avg_response_time_ms' => $query->avg('response_time_ms'),
            'min_response_time_ms' => $query->min('response_time_ms'),
            'max_response_time_ms' => $query->max('response_time_ms'),
            'response_source_breakdown' => (clone $query)
                ->selectRaw('response_source, COUNT(*) as count, AVG(response_time_ms) as avg_time')
                ->groupBy('response_source')
                ->get()
                ->keyBy('response_source')
                ->toArray(),
        ];
    }

    /**
     * Clean up old analytics data (for GDPR/storage management)
     */
    public function cleanup(int $daysToKeep = 90): array
    {
        $cutoff = now()->subDays($daysToKeep);

        $analyticsDeleted = ChatbotAnalytics::where('created_at', '<', $cutoff)->delete();
        $rateLimitsDeleted = ChatbotRateLimit::cleanup();
        
        // Archive old conversations instead of deleting
        $conversationsArchived = ChatbotConversation::where('last_activity_at', '<', $cutoff)
            ->where('status', '!=', 'archived')
            ->update(['status' => 'archived']);

        return [
            'analytics_deleted' => $analyticsDeleted,
            'rate_limits_deleted' => $rateLimitsDeleted,
            'conversations_archived' => $conversationsArchived,
        ];
    }
}
