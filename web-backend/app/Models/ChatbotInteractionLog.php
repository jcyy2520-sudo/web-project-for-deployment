<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * ChatbotInteractionLog - Logs every chatbot interaction for analytics and feedback
 */
class ChatbotInteractionLog extends Model
{
    protected $table = 'chatbot_interaction_logs';

    protected $fillable = [
        'interaction_id',
        'user_id',
        'conversation_id',
        'session_id',
        'user_message',
        'bot_response',
        'intent_detected',
        'confidence_score',
        'context_sources',
        'llm_provider',
        'processing_time_ms',
        'response_source',
        'was_fallback',
        'has_feedback',
        'feedback_rating',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:4',
        'context_sources' => 'array',
        'processing_time_ms' => 'integer',
        'was_fallback' => 'boolean',
        'has_feedback' => 'boolean',
        'feedback_rating' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who sent this message
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the feedback for this interaction
     */
    public function feedback(): HasOne
    {
        return $this->hasOne(ChatbotFeedback::class, 'interaction_id', 'interaction_id');
    }

    /**
     * Scope for interactions that need review (fallbacks or low ratings)
     */
    public function scopeNeedsReview($query)
    {
        return $query->where(function($q) {
            $q->where('was_fallback', true)
              ->orWhere('feedback_rating', '<=', 2);
        });
    }

    /**
     * Scope for successful interactions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('was_fallback', false)
                     ->where(function($q) {
                         $q->whereNull('feedback_rating')
                           ->orWhere('feedback_rating', '>=', 4);
                     });
    }
}
