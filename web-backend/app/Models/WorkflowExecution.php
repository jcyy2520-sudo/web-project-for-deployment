<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowExecution extends Model
{
    protected $table = 'workflow_executions';
    
    protected $fillable = [
        'workflow_id',
        'user_id',
        'workflow_name',
        'steps',
        'context',
        'results',
        'status',
        'error_message',
        'failed_step',
        'total_steps',
        'completed_steps',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'steps' => 'array',
        'context' => 'array',
        'results' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
