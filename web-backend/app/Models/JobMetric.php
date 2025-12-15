<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class JobMetric extends Model
{
    protected $fillable = [
        'job_name',
        'job_class',
        'queue',
        'status',
        'attempts',
        'max_attempts',
        'payload',
        'failure_reason',
        'output',
        'duration_seconds',
        'started_at',
        'completed_at',
        'failed_at',
        'will_retry_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'will_retry_at' => 'datetime',
    ];

    // Scopes
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    public function scopeByQueue(Builder $query, string $queue): Builder
    {
        return $query->where('queue', $queue);
    }

    public function scopeByName(Builder $query, string $jobName): Builder
    {
        return $query->where('job_name', 'like', "%{$jobName}%");
    }

    // Attributes
    public function getDurationFormattedAttribute(): string
    {
        return $this->duration_seconds
            ? gmdate('H:i:s', $this->duration_seconds)
            : 'N/A';
    }

    public function getIsStuckAttribute(): bool
    {
        return $this->status === 'processing' && $this->started_at->addMinutes(30) < now();
    }

    public function getIsRetryableAttribute(): bool
    {
        return $this->status === 'failed' && $this->attempts < $this->max_attempts;
    }

    // Methods
    public function markStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markCompleted(string $output = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'duration_seconds' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
            'output' => $output,
        ]);
    }

    public function markFailed(string $reason, bool $willRetry = false): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
            'duration_seconds' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
            'will_retry_at' => $willRetry ? now()->addMinutes(5) : null,
        ]);
    }

    public function retry(): void
    {
        $this->update([
            'status' => 'retried',
            'attempts' => $this->attempts + 1,
            'will_retry_at' => null,
        ]);
    }

    // Statistics
    public static function getStatistics(int $hours = 24): array
    {
        $query = self::recent($hours);

        return [
            'total' => (clone $query)->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'processing' => (clone $query)->where('status', 'processing')->count(),
            'average_duration' => (clone $query)->whereNotNull('duration_seconds')->avg('duration_seconds'),
            'success_rate' => self::calculateSuccessRate($query),
            'stuck_jobs' => (clone $query)->where('status', 'processing')
                ->where('started_at', '<', now()->subMinutes(30))
                ->count(),
        ];
    }

    private static function calculateSuccessRate(Builder $query): float
    {
        $total = (clone $query)->count();
        if ($total === 0) return 0;

        $completed = (clone $query)->where('status', 'completed')->count();
        return round(($completed / $total) * 100, 2);
    }
}
