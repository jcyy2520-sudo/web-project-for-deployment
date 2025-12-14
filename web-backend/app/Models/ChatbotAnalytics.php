<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotAnalytics extends Model
{
    protected $table = 'chatbot_analytics';
    
    protected $fillable = [
        'user_id',
        'conversation_id',
        'session_id',
        'user_message',
        'bot_response',
        'detected_intent',
        'detected_language',
        'entities_extracted',
        'sentiment',
        'sentiment_score',
        'is_priority',
        'priority_reason',
        'response_time_ms',
        'response_source',
        'confidence_score',
        'was_successful',
        'failure_reason',
        'is_out_of_scope',
        'action_type',
        'action_executed',
        'action_result',
        'contains_profanity',
        'is_spam',
        'is_rate_limited',
        'user_role',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'entities_extracted' => 'array',
        'action_result' => 'array',
        'is_priority' => 'boolean',
        'was_successful' => 'boolean',
        'is_out_of_scope' => 'boolean',
        'action_executed' => 'boolean',
        'contains_profanity' => 'boolean',
        'is_spam' => 'boolean',
        'is_rate_limited' => 'boolean',
        'confidence_score' => 'float',
        'sentiment_score' => 'integer',
        'response_time_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get analytics summary for a time period
     */
    public static function getSummary(string $period = 'day', ?int $userId = null): array
    {
        $query = self::query();
        
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
            case 'month':
                $query->where('created_at', '>=', now()->subMonth());
                break;
        }
        
        if ($userId) {
            $query->where('user_id', $userId);
        }

        return [
            'total_messages' => $query->count(),
            'successful' => (clone $query)->where('was_successful', true)->count(),
            'failed' => (clone $query)->where('was_successful', false)->count(),
            'out_of_scope' => (clone $query)->where('is_out_of_scope', true)->count(),
            'rate_limited' => (clone $query)->where('is_rate_limited', true)->count(),
            'contains_profanity' => (clone $query)->where('contains_profanity', true)->count(),
            'priority_messages' => (clone $query)->where('is_priority', true)->count(),
            'avg_response_time_ms' => (clone $query)->whereNotNull('response_time_ms')->avg('response_time_ms'),
            'sentiment_breakdown' => (clone $query)
                ->selectRaw('sentiment, COUNT(*) as count')
                ->groupBy('sentiment')
                ->pluck('count', 'sentiment')
                ->toArray(),
            'top_intents' => (clone $query)
                ->whereNotNull('detected_intent')
                ->selectRaw('detected_intent, COUNT(*) as count')
                ->groupBy('detected_intent')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'detected_intent')
                ->toArray(),
            'language_breakdown' => (clone $query)
                ->selectRaw('detected_language, COUNT(*) as count')
                ->groupBy('detected_language')
                ->pluck('count', 'detected_language')
                ->toArray(),
        ];
    }

    /**
     * Get failed requests for review
     */
    public static function getFailedRequests(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('was_successful', false)
            ->orWhere('is_out_of_scope', true)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
