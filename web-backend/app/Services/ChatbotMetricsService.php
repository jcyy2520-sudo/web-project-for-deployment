<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatbotConversation;
use App\Models\ChatbotAnalytics;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * ChatbotMetricsService - Monitoring and analytics
 * 
 * Tracks:
 * - Conversation quality metrics
 * - User satisfaction
 * - Performance bottlenecks
 * - Response times
 * - Engagement metrics
 * - Error rates
 */
class ChatbotMetricsService
{
    private const METRICS_CACHE_TTL = 3600; // 1 hour
    private const METRICS_KEY = 'chatbot_metrics:';

    /**
     * Record message metrics
     */
    public function recordMessageMetrics(
        int $userId,
        string $conversationId,
        string $messageContent,
        int $responseTimeMs,
        array $metadata = []
    ): void {
        try {
            $metrics = [
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'message_length' => strlen($messageContent),
                'response_time_ms' => $responseTimeMs,
                'timestamp' => now()->toDateTimeString(),
                'metadata' => $metadata,
            ];

            // Log to database
            ChatbotAnalytics::create([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'metric_type' => 'message',
                'metric_data' => $metrics,
                'recorded_at' => now(),
            ]);

            // Update cache for aggregation
            $this->updateMetricCache($metrics);

            Log::debug("Message metrics recorded", $metrics);
        } catch (\Exception $e) {
            Log::error('Failed to record message metrics: ' . $e->getMessage());
        }
    }

    /**
     * Record action metrics
     */
    public function recordActionMetrics(
        int $userId,
        string $actionType,
        bool $success,
        int $executionTimeMs,
        array $context = []
    ): void {
        try {
            $metrics = [
                'user_id' => $userId,
                'action_type' => $actionType,
                'success' => $success,
                'execution_time_ms' => $executionTimeMs,
                'timestamp' => now()->toDateTimeString(),
                'context' => $context,
            ];

            ChatbotAnalytics::create([
                'user_id' => $userId,
                'metric_type' => 'action',
                'metric_data' => $metrics,
                'recorded_at' => now(),
            ]);

            Log::debug("Action metrics recorded", $metrics);
        } catch (\Exception $e) {
            Log::error('Failed to record action metrics: ' . $e->getMessage());
        }
    }

    /**
     * Record error metrics
     */
    public function recordErrorMetrics(
        int $userId,
        string $errorType,
        string $errorMessage,
        array $context = []
    ): void {
        try {
            $metrics = [
                'user_id' => $userId,
                'error_type' => $errorType,
                'error_message' => $errorMessage,
                'timestamp' => now()->toDateTimeString(),
                'context' => $context,
            ];

            ChatbotAnalytics::create([
                'user_id' => $userId,
                'metric_type' => 'error',
                'metric_data' => $metrics,
                'recorded_at' => now(),
            ]);

            Log::error("Error metrics recorded: {$errorType}", $metrics);
        } catch (\Exception $e) {
            Log::error('Failed to record error metrics: ' . $e->getMessage());
        }
    }

    /**
     * Get conversation quality score
     */
    public function getConversationQualityScore(string $conversationId): array
    {
        try {
            $conversation = ChatbotConversation::where('conversation_id', $conversationId)->first();

            if (!$conversation) {
                return ['score' => 0, 'reason' => 'Conversation not found'];
            }

            $score = 0;
            $factors = [];

            // Message count (quality often correlates with engagement)
            $messageCount = $conversation->message_count ?? 0;
            $messageFactor = min(100, $messageCount * 5);
            $score += $messageFactor * 0.2;
            $factors['message_engagement'] = $messageFactor;

            // Sentiment (positive sentiment = better quality)
            $sentimentScore = $this->getSentimentScore($conversationId);
            $score += $sentimentScore * 0.3;
            $factors['sentiment'] = $sentimentScore;

            // Response time (faster = better, generally)
            $avgResponseTime = $this->getAverageResponseTime($conversationId);
            $responseTimeFactor = $this->calculateResponseTimeFactor($avgResponseTime);
            $score += $responseTimeFactor * 0.2;
            $factors['response_time'] = $responseTimeFactor;

            // Error rate (fewer errors = better)
            $errorRate = $this->getErrorRate($conversationId);
            $errorFactor = (100 - ($errorRate * 100)) * 0.2;
            $score += $errorFactor * 0.15;
            $factors['error_rate'] = $errorFactor;

            // User satisfaction (if available)
            $satisfactionScore = $this->getUserSatisfactionScore($conversationId);
            $score += $satisfactionScore * 0.15;
            $factors['satisfaction'] = $satisfactionScore;

            // Normalize to 0-100
            $finalScore = min(100, $score);

            return [
                'score' => round($finalScore, 2),
                'factors' => $factors,
                'grade' => $this->getQualityGrade($finalScore),
                'message_count' => $messageCount,
                'duration_seconds' => $conversation->last_activity_at ? 
                    $conversation->created_at->diffInSeconds($conversation->last_activity_at) : 0,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to calculate quality score: ' . $e->getMessage());
            return ['score' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get user satisfaction rating
     */
    public function recordUserSatisfaction(string $conversationId, int $rating, $feedback = null): array
    {
        try {
            if ($rating < 1 || $rating > 5) {
                return ['success' => false, 'error' => 'Rating must be between 1 and 5'];
            }

            $conversation = ChatbotConversation::where('conversation_id', $conversationId)->first();

            if (!$conversation) {
                return ['success' => false, 'error' => 'Conversation not found'];
            }

            $contextData = $conversation->context_data ?? [];
            $contextData['satisfaction_rating'] = $rating;
            $contextData['satisfaction_feedback'] = $feedback;
            $contextData['satisfaction_recorded_at'] = now()->toDateTimeString();

            $conversation->update(['context_data' => $contextData]);

            // Record metric
            ChatbotAnalytics::create([
                'conversation_id' => $conversationId,
                'user_id' => $conversation->user_id,
                'metric_type' => 'satisfaction',
                'metric_data' => [
                    'rating' => $rating,
                    'feedback' => $feedback,
                    'timestamp' => now()->toDateTimeString(),
                ],
                'recorded_at' => now(),
            ]);

            return ['success' => true, 'rating' => $rating];
        } catch (\Exception $e) {
            Log::error('Failed to record satisfaction: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get performance metrics for period
     */
    public function getPerformanceMetrics(string $period = 'day'): array
    {
        try {
            $startDate = $this->getStartDate($period);

            $metrics = [
                'period' => $period,
                'start_date' => $startDate->toDateTimeString(),
                'total_conversations' => 0,
                'total_messages' => 0,
                'average_quality_score' => 0,
                'average_response_time_ms' => 0,
                'error_rate' => 0,
                'average_satisfaction' => 0,
            ];

            // Get conversation data
            $conversations = ChatbotConversation::where('created_at', '>=', $startDate)->get();
            $metrics['total_conversations'] = $conversations->count();

            if ($conversations->count() === 0) {
                return $metrics;
            }

            // Calculate aggregates
            $totalMessages = 0;
            $totalQualityScore = 0;
            $totalResponseTime = 0;
            $totalSatisfaction = 0;
            $satisfactionCount = 0;

            foreach ($conversations as $conversation) {
                $totalMessages += $conversation->message_count ?? 0;
                $qualityScore = $this->getConversationQualityScore($conversation->conversation_id);
                $totalQualityScore += $qualityScore['score'] ?? 0;
                $totalResponseTime += $this->getAverageResponseTime($conversation->conversation_id);

                // Get satisfaction if available
                $contextData = $conversation->context_data ?? [];
                if (isset($contextData['satisfaction_rating'])) {
                    $totalSatisfaction += $contextData['satisfaction_rating'];
                    $satisfactionCount++;
                }
            }

            $metrics['total_messages'] = $totalMessages;
            $metrics['average_quality_score'] = round($totalQualityScore / $conversations->count(), 2);
            $metrics['average_response_time_ms'] = round($totalResponseTime / $conversations->count(), 0);

            if ($satisfactionCount > 0) {
                $metrics['average_satisfaction'] = round($totalSatisfaction / $satisfactionCount, 2);
            }

            $metrics['error_rate'] = round($this->getAggregateErrorRate($startDate), 2);

            return $metrics;
        } catch (\Exception $e) {
            Log::error('Failed to get performance metrics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Identify performance bottlenecks
     */
    public function identifyBottlenecks(string $period = 'day'): array
    {
        try {
            $startDate = $this->getStartDate($period);

            $bottlenecks = [];

            // Slow response times
            $slowMessages = ChatbotAnalytics::where('metric_type', 'message')
                ->where('recorded_at', '>=', $startDate)
                ->orderBy('metric_data->response_time_ms', 'desc')
                ->limit(10)
                ->get();

            if ($slowMessages->count() > 0) {
                $avgSlowTime = $slowMessages->avg(function ($m) {
                    return $m->metric_data['response_time_ms'] ?? 0;
                });

                if ($avgSlowTime > 2000) {
                    $bottlenecks[] = [
                        'type' => 'slow_response',
                        'severity' => 'high',
                        'avg_response_ms' => round($avgSlowTime, 0),
                        'message' => 'Response times are consistently slow',
                        'recommendation' => 'Check database queries and LLM API performance',
                    ];
                }
            }

            // High error rate
            $errorRate = $this->getAggregateErrorRate($startDate);
            if ($errorRate > 0.05) {
                $bottlenecks[] = [
                    'type' => 'high_error_rate',
                    'severity' => 'critical',
                    'error_rate_percent' => round($errorRate * 100, 2),
                    'message' => 'Error rate is higher than acceptable',
                    'recommendation' => 'Review error logs and API integrations',
                ];
            }

            // Low satisfaction
            $avgSatisfaction = $this->getAverageSatisfaction($startDate);
            if ($avgSatisfaction > 0 && $avgSatisfaction < 3) {
                $bottlenecks[] = [
                    'type' => 'low_satisfaction',
                    'severity' => 'medium',
                    'avg_rating' => round($avgSatisfaction, 2),
                    'message' => 'User satisfaction scores are low',
                    'recommendation' => 'Review conversation quality and user feedback',
                ];
            }

            return $bottlenecks;
        } catch (\Exception $e) {
            Log::error('Failed to identify bottlenecks: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get engagement metrics
     */
    public function getEngagementMetrics(int $userId): array
    {
        try {
            $conversations = ChatbotConversation::where('user_id', $userId)->get();

            return [
                'total_conversations' => $conversations->count(),
                'total_messages' => $conversations->sum('message_count') ?? 0,
                'average_conversation_length' => $conversations->count() > 0 ? 
                    round($conversations->avg('message_count') ?? 0, 1) : 0,
                'last_conversation_at' => $conversations->max('last_activity_at'),
                'first_conversation_at' => $conversations->min('created_at'),
                'return_user' => $conversations->count() > 1,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get engagement metrics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Private helper methods
     */

    private function updateMetricCache(array $metrics): void
    {
        $cacheKey = self::METRICS_KEY . now()->format('Y-m-d');
        $cached = Cache::get($cacheKey, []);
        $cached[] = $metrics;
        Cache::put($cacheKey, $cached, self::METRICS_CACHE_TTL);
    }

    private function getSentimentScore(string $conversationId): float
    {
        try {
            $conversation = ChatbotConversation::where('conversation_id', $conversationId)->first();
            $sentiment = $conversation->average_sentiment_score ?? 0;
            // Normalize to 0-100
            return ($sentiment + 1) * 50;
        } catch (\Exception $e) {
            return 50;
        }
    }

    private function getAverageResponseTime(string $conversationId): int
    {
        try {
            $analytics = ChatbotAnalytics::where('conversation_id', $conversationId)
                ->where('metric_type', 'message')
                ->get();

            if ($analytics->count() === 0) {
                return 0;
            }

            return round($analytics->avg(function ($a) {
                return $a->metric_data['response_time_ms'] ?? 0;
            }), 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getErrorRate(string $conversationId): float
    {
        try {
            $total = ChatbotAnalytics::where('conversation_id', $conversationId)->count();
            if ($total === 0) return 0;

            $errors = ChatbotAnalytics::where('conversation_id', $conversationId)
                ->where('metric_type', 'error')
                ->count();

            return $errors / $total;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getUserSatisfactionScore(string $conversationId): float
    {
        try {
            $conversation = ChatbotConversation::where('conversation_id', $conversationId)->first();
            $rating = $conversation->context_data['satisfaction_rating'] ?? null;

            if (!$rating) {
                return 60; // Default neutral score
            }

            return ($rating / 5) * 100;
        } catch (\Exception $e) {
            return 60;
        }
    }

    private function getQualityGrade(float $score): string
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }

    private function calculateResponseTimeFactor(int $avgTimeMs): float
    {
        // Good: < 500ms, Acceptable: 500-2000ms, Poor: > 2000ms
        if ($avgTimeMs < 500) return 100;
        if ($avgTimeMs < 2000) return 100 - (($avgTimeMs - 500) / 1500) * 50;
        return 50 - min(50, ($avgTimeMs - 2000) / 1000);
    }

    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            'hour' => now()->subHour(),
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subDay(),
        };
    }

    private function getAggregateErrorRate(Carbon $startDate): float
    {
        try {
            $total = ChatbotAnalytics::where('recorded_at', '>=', $startDate)->count();
            if ($total === 0) return 0;

            $errors = ChatbotAnalytics::where('recorded_at', '>=', $startDate)
                ->where('metric_type', 'error')
                ->count();

            return $errors / $total;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getAverageSatisfaction(Carbon $startDate): float
    {
        try {
            $conversations = ChatbotConversation::where('created_at', '>=', $startDate)->get();
            $ratings = [];

            foreach ($conversations as $conversation) {
                $rating = $conversation->context_data['satisfaction_rating'] ?? null;
                if ($rating) {
                    $ratings[] = $rating;
                }
            }

            return count($ratings) > 0 ? array_sum($ratings) / count($ratings) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
