<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLongTermMemory extends Model
{
    protected $table = 'user_long_term_memory';
    
    protected $fillable = [
        'user_id',
        'key',
        'value',
        'category',
        'access_count',
        'relevance_score',
        'last_accessed_at',
        'expires_at',
    ];

    protected $casts = [
        'last_accessed_at' => 'datetime',
        'expires_at' => 'datetime',
        'relevance_score' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}
