<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatbotConversation extends Model
{
    protected $table = 'chatbot_conversations';
    
    protected $fillable = [
        'conversation_id',
        'user_id',
        'session_id',
        'title',
        'summary',
        'detected_language',
        'primary_intent',
        'context_data',
        'message_count',
        'user_message_count',
        'bot_message_count',
        'overall_sentiment',
        'average_sentiment_score',
        'status',
        'was_rate_limited',
        'requires_human_follow_up',
        'last_activity_at',
    ];

    protected $casts = [
        'context_data' => 'array',
        'message_count' => 'integer',
        'user_message_count' => 'integer',
        'bot_message_count' => 'integer',
        'average_sentiment_score' => 'float',
        'was_rate_limited' => 'boolean',
        'requires_human_follow_up' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id', 'conversation_id');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(ChatbotAnalytics::class, 'conversation_id', 'conversation_id');
    }

    /**
     * Get or create a conversation record
     */
    public static function getOrCreate(string $conversationId, ?int $userId, ?string $sessionId): self
    {
        return self::firstOrCreate(
            ['conversation_id' => $conversationId],
            [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'status' => 'active',
                'last_activity_at' => now(),
            ]
        );
    }

    /**
     * Update conversation after a message
     */
    public function recordMessage(string $role, string $message, array $analytics = []): void
    {
        $this->increment('message_count');
        
        if ($role === 'user') {
            $this->increment('user_message_count');
        } else {
            $this->increment('bot_message_count');
        }

        // Update sentiment tracking
        if (!empty($analytics['sentiment_score'])) {
            $totalScore = ($this->average_sentiment_score * ($this->message_count - 1)) + $analytics['sentiment_score'];
            $this->average_sentiment_score = $totalScore / $this->message_count;
        }

        if (!empty($analytics['sentiment'])) {
            $this->overall_sentiment = $analytics['sentiment'];
        }

        if (!empty($analytics['detected_language'])) {
            $this->detected_language = $analytics['detected_language'];
        }

        if (!empty($analytics['detected_intent']) && !$this->primary_intent) {
            $this->primary_intent = $analytics['detected_intent'];
        }

        // Generate title from first user message
        if (!$this->title && $role === 'user') {
            $this->title = \Illuminate\Support\Str::limit($message, 50);
        }

        // Check if needs human follow-up
        if (($analytics['sentiment_score'] ?? 0) >= 4 || ($analytics['is_priority'] ?? false)) {
            $this->requires_human_follow_up = true;
        }

        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Mark as rate limited
     */
    public function markRateLimited(): void
    {
        $this->update([
            'status' => 'rate_limited',
            'was_rate_limited' => true,
        ]);
    }

    /**
     * Update context data
     */
    public function updateContext(array $data): void
    {
        $currentContext = $this->context_data ?? [];
        $this->context_data = array_merge($currentContext, $data);
        $this->save();
    }

    /**
     * Get conversations needing human attention
     */
    public static function getNeedingAttention(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('requires_human_follow_up', true)
            ->where('status', '!=', 'completed')
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('last_activity_at')
            ->get();
    }

    /**
     * Get active conversations count
     */
    public static function getActiveCount(?int $userId = null): int
    {
        $query = self::where('status', 'active')
            ->where('last_activity_at', '>=', now()->subHour());
            
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        return $query->count();
    }
}
