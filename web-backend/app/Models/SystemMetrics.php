<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsCollection;

class SystemMetrics extends Model
{
    protected $table = 'system_metrics';

    protected $fillable = [
        'timestamp',
        'cpu_usage',
        'memory_usage',
        'memory_total',
        'disk_usage',
        'disk_total',
        'disk_free',
        'load_average_1min',
        'load_average_5min',
        'load_average_15min',
        'processes_running',
        'network_in_bytes',
        'network_out_bytes',
        'database_connections',
        'database_size_mb',
        'cache_memory_usage_mb',
        'active_sessions',
        'pending_jobs',
        'failed_jobs',
        'metadata',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'cpu_usage' => 'float',
        'memory_usage' => 'integer',
        'memory_total' => 'integer',
        'disk_usage' => 'integer',
        'disk_total' => 'integer',
        'disk_free' => 'integer',
        'load_average_1min' => 'float',
        'load_average_5min' => 'float',
        'load_average_15min' => 'float',
        'network_in_bytes' => 'integer',
        'network_out_bytes' => 'integer',
        'database_connections' => 'integer',
        'database_size_mb' => 'float',
        'cache_memory_usage_mb' => 'float',
        'active_sessions' => 'integer',
        'pending_jobs' => 'integer',
        'failed_jobs' => 'integer',
        'metadata' => AsCollection::class,
    ];

    /**
     * Get metrics within the last N minutes
     */
    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('timestamp', '>=', now()->subMinutes($minutes))
            ->orderBy('timestamp', 'asc');
    }

    /**
     * Get metrics for a specific hour
     */
    public function scopeForHour($query, int $hoursAgo = 0)
    {
        $date = now()->subHours($hoursAgo);
        return $query->whereDate('timestamp', $date->toDateString())
            ->whereHour('timestamp', $date->hour)
            ->orderBy('timestamp', 'asc');
    }

    /**
     * Get aggregated metrics for a time range
     */
    public function scopeAggregated($query, int $minutes = 60)
    {
        return $query->selectRaw('
            MIN(timestamp) as start_time,
            MAX(timestamp) as end_time,
            AVG(cpu_usage) as avg_cpu,
            MAX(cpu_usage) as max_cpu,
            MIN(cpu_usage) as min_cpu,
            AVG(memory_usage) as avg_memory,
            MAX(memory_usage) as max_memory,
            AVG(disk_usage) as avg_disk,
            MAX(disk_usage) as max_disk,
            AVG(load_average_1min) as avg_load,
            SUM(network_in_bytes) as total_in_bytes,
            SUM(network_out_bytes) as total_out_bytes,
            COUNT(*) as sample_count
        ')
            ->where('timestamp', '>=', now()->subMinutes($minutes))
            ->groupRaw('DATE(timestamp), HOUR(timestamp)');
    }

    /**
     * Check if system is under stress
     */
    public function isUnderStress(): bool
    {
        return $this->cpu_usage > 80 || 
               ($this->memory_usage / $this->memory_total * 100) > 85 ||
               ($this->disk_usage / $this->disk_total * 100) > 90;
    }

    /**
     * Get health status
     */
    public function getHealthStatus(): string
    {
        $cpu_percent = $this->cpu_usage;
        $memory_percent = $this->memory_usage / $this->memory_total * 100;
        $disk_percent = $this->disk_usage / $this->disk_total * 100;

        if ($cpu_percent > 85 || $memory_percent > 90 || $disk_percent > 95) {
            return 'critical';
        }
        if ($cpu_percent > 70 || $memory_percent > 80 || $disk_percent > 85) {
            return 'warning';
        }
        return 'healthy';
    }
}
