<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChatbotFeedback - Stores user feedback on chatbot responses
 * 
 * Used for:
 * - Tracking satisfaction metrics
 * - Identifying problematic responses
 * - Collecting corrections for retraining
 */
class ChatbotFeedback extends Model
{
    protected $table = 'chatbot_feedback';

    protected $fillable = [
        'interaction_id',
        'user_id',
        'rating',
        'is_helpful',
        'is_correct',
        'correction_text',
        'expected_response',
        'feedback_category',
        'comments',
        'correction_applied',
        'applied_at',
        'submitted_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_helpful' => 'boolean',
        'is_correct' => 'boolean',
        'correction_applied' => 'boolean',
        'applied_at' => 'datetime',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Feedback categories for classification
     */
    public const CATEGORIES = [
        'wrong_info' => 'Information was incorrect',
        'outdated' => 'Information was outdated',
        'unclear' => 'Response was unclear or confusing',
        'incomplete' => 'Response was incomplete',
        'off_topic' => 'Response was off-topic',
        'too_long' => 'Response was too long',
        'too_short' => 'Response was too short',
        'rude' => 'Response tone was inappropriate',
        'helpful' => 'Response was very helpful',
        'other' => 'Other feedback',
    ];

    /**
     * Get the user who submitted feedback
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the interaction this feedback is for
     */
    public function interactionLog(): BelongsTo
    {
        return $this->belongsTo(ChatbotInteractionLog::class, 'interaction_id', 'interaction_id');
    }

    /**
     * Scope for negative feedback (needs attention)
     */
    public function scopeNegative($query)
    {
        return $query->where(function($q) {
            $q->where('is_helpful', false)
              ->orWhere('is_correct', false)
              ->orWhere('rating', '<=', 2);
        });
    }

    /**
     * Scope for feedback with corrections (for retraining)
     */
    public function scopeWithCorrections($query)
    {
        return $query->where(function($q) {
            $q->whereNotNull('correction_text')
              ->orWhereNotNull('expected_response');
        });
    }

    /**
     * Scope for unapplied corrections
     */
    public function scopePendingCorrections($query)
    {
        return $query->withCorrections()
                     ->where('correction_applied', false);
    }
}
