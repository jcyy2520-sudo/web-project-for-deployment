<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationSummary extends Model
{
    protected $table = 'conversation_summaries';
    
    protected $fillable = [
        'conversation_id',
        'user_id',
        'summary',
        'key_points',
        'topics',
        'entities',
        'message_count',
        'tokens_used',
        'sentiment',
        'sentiment_score',
        'summarized_at',
    ];

    protected $casts = [
        'key_points' => 'array',
        'topics' => 'array',
        'entities' => 'array',
        'sentiment_score' => 'float',
        'summarized_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
