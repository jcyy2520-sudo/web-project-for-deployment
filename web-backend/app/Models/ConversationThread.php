<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationThread extends Model
{
    protected $table = 'conversation_threads';
    
    protected $fillable = [
        'conversation_id',
        'session_id',
        'user_id',
        'topic',
        'title',
        'description',
        'metadata',
        'status',
        'is_pinned',
        'tags',
        'last_activity_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'tags' => 'array',
        'is_pinned' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
