<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ChatbotRateLimit extends Model
{
    protected $table = 'chatbot_rate_limits';
    
    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'conversation_id',
        'message_count',
        'window_start',
        'window_end',
        'is_blocked',
        'blocked_until',
        'block_reason',
    ];

    protected $casts = [
        'message_count' => 'integer',
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'is_blocked' => 'boolean',
        'blocked_until' => 'datetime',
    ];

    // Rate limit configuration
    public const MESSAGES_PER_CONVERSATION = 20;
    public const MESSAGES_PER_MINUTE = 5;
    public const MESSAGES_PER_HOUR = 50;
    public const BLOCK_DURATION_MINUTES = 5;
    public const SPAM_THRESHOLD = 3; // Messages in 10 seconds
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a user/session is rate limited
     */
    public static function isRateLimited(?int $userId, ?string $sessionId, ?string $ipAddress, ?string $conversationId = null): array
    {
        $identifier = $userId ?? $sessionId ?? $ipAddress;
        
        if (!$identifier) {
            return ['limited' => false, 'reason' => null, 'remaining' => self::MESSAGES_PER_CONVERSATION];
        }

        // Check for blocked status
        $blocked = self::where(function($q) use ($userId, $sessionId, $ipAddress) {
                if ($userId) $q->orWhere('user_id', $userId);
                if ($sessionId) $q->orWhere('session_id', $sessionId);
                if ($ipAddress) $q->orWhere('ip_address', $ipAddress);
            })
            ->where('is_blocked', true)
            ->where('blocked_until', '>', now())
            ->first();

        if ($blocked) {
            $remainingSeconds = now()->diffInSeconds($blocked->blocked_until);
            return [
                'limited' => true,
                'reason' => 'blocked',
                'message' => "You've been temporarily blocked due to {$blocked->block_reason}. Please wait " . ceil($remainingSeconds / 60) . " minutes.",
                'blocked_until' => $blocked->blocked_until->toIso8601String(),
                'remaining' => 0,
                'must_start_new' => true,
            ];
        }

        // Check conversation limit (20 messages per conversation)
        if ($conversationId) {
            $conversationMessages = \App\Models\ChatMessage::where('conversation_id', $conversationId)
                ->where('role', 'user')
                ->count();

            if ($conversationMessages >= self::MESSAGES_PER_CONVERSATION) {
                return [
                    'limited' => true,
                    'reason' => 'conversation_limit',
                    'message' => "You've reached the message limit for this conversation. Please start a new conversation to continue.",
                    'remaining' => 0,
                    'must_start_new' => true,
                    'conversation_message_count' => $conversationMessages,
                ];
            }

            $remaining = self::MESSAGES_PER_CONVERSATION - $conversationMessages;
        } else {
            $remaining = self::MESSAGES_PER_CONVERSATION;
        }

        // Check per-minute rate limit
        $recentMinute = self::where(function($q) use ($userId, $sessionId, $ipAddress) {
                if ($userId) $q->orWhere('user_id', $userId);
                if ($sessionId) $q->orWhere('session_id', $sessionId);
                if ($ipAddress) $q->orWhere('ip_address', $ipAddress);
            })
            ->where('window_start', '>=', now()->subMinute())
            ->sum('message_count');

        if ($recentMinute >= self::MESSAGES_PER_MINUTE) {
            return [
                'limited' => true,
                'reason' => 'rate_limit',
                'message' => 'Please slow down. You can send up to 5 messages per minute.',
                'remaining' => 0,
                'retry_after' => 60,
            ];
        }

        // Check spam detection (3 messages in 10 seconds)
        $recentSeconds = self::where(function($q) use ($userId, $sessionId, $ipAddress) {
                if ($userId) $q->orWhere('user_id', $userId);
                if ($sessionId) $q->orWhere('session_id', $sessionId);
                if ($ipAddress) $q->orWhere('ip_address', $ipAddress);
            })
            ->where('window_start', '>=', now()->subSeconds(10))
            ->sum('message_count');

        if ($recentSeconds >= self::SPAM_THRESHOLD) {
            // Block for spam behavior
            self::blockUser($userId, $sessionId, $ipAddress, 'spam_detection', self::BLOCK_DURATION_MINUTES);
            
            return [
                'limited' => true,
                'reason' => 'spam_detected',
                'message' => 'Spam detected. You\'ve been temporarily blocked for ' . self::BLOCK_DURATION_MINUTES . ' minutes.',
                'blocked_until' => now()->addMinutes(self::BLOCK_DURATION_MINUTES)->toIso8601String(),
                'remaining' => 0,
                'must_start_new' => true,
            ];
        }

        return [
            'limited' => false,
            'reason' => null,
            'remaining' => $remaining,
            'conversation_remaining' => $remaining,
            'minute_remaining' => self::MESSAGES_PER_MINUTE - $recentMinute,
        ];
    }

    /**
     * Increment message count
     */
    public static function incrementCount(?int $userId, ?string $sessionId, ?string $ipAddress, ?string $conversationId): void
    {
        $record = self::firstOrCreate(
            [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'ip_address' => $ipAddress,
                'window_start' => now()->startOfMinute(),
            ],
            [
                'conversation_id' => $conversationId,
                'message_count' => 0,
                'window_end' => now()->addMinute(),
            ]
        );

        $record->increment('message_count');
        $record->conversation_id = $conversationId;
        $record->save();
    }

    /**
     * Block a user/session
     */
    public static function blockUser(?int $userId, ?string $sessionId, ?string $ipAddress, string $reason, int $minutes = 5): void
    {
        self::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'ip_address' => $ipAddress,
            'message_count' => 0,
            'window_start' => now(),
            'is_blocked' => true,
            'blocked_until' => now()->addMinutes($minutes),
            'block_reason' => $reason,
        ]);
    }

    /**
     * Get rate limit status summary
     */
    public static function getStatus(?int $userId, ?string $sessionId, ?string $conversationId): array
    {
        $conversationMessages = 0;
        
        if ($conversationId) {
            $conversationMessages = \App\Models\ChatMessage::where('conversation_id', $conversationId)
                ->where('role', 'user')
                ->count();
        }

        return [
            'conversation_limit' => self::MESSAGES_PER_CONVERSATION,
            'conversation_used' => $conversationMessages,
            'conversation_remaining' => max(0, self::MESSAGES_PER_CONVERSATION - $conversationMessages),
            'messages_per_minute' => self::MESSAGES_PER_MINUTE,
            'should_start_new' => $conversationMessages >= self::MESSAGES_PER_CONVERSATION,
        ];
    }

    /**
     * Clean up old rate limit records
     */
    public static function cleanup(): int
    {
        return self::where('window_start', '<', now()->subDay())
            ->where(function($q) {
                $q->where('is_blocked', false)
                  ->orWhere('blocked_until', '<', now());
            })
            ->delete();
    }
}
