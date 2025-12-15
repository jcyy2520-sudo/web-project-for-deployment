<?php

namespace App\Services;

use App\Models\SystemMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SystemMetricsService
{
    /**
     * Collect current system metrics
     */
    public function collectMetrics(): SystemMetrics
    {
        $metrics = new SystemMetrics();

        // CPU usage (using load average on Linux, estimation on Windows)
        $metrics->cpu_usage = $this->getCpuUsage();

        // Memory usage
        $memInfo = $this->getMemoryInfo();
        $metrics->memory_usage = $memInfo['used'];
        $metrics->memory_total = $memInfo['total'];

        // Disk usage
        $diskInfo = $this->getDiskInfo();
        $metrics->disk_usage = $diskInfo['used'];
        $metrics->disk_total = $diskInfo['total'];
        $metrics->disk_free = $diskInfo['free'];

        // Load average
        $load = $this->getLoadAverage();
        $metrics->load_average_1min = $load[0] ?? 0;
        $metrics->load_average_5min = $load[1] ?? 0;
        $metrics->load_average_15min = $load[2] ?? 0;

        // Running processes
        $metrics->processes_running = $this->getProcessCount();

        // Network stats
        $network = $this->getNetworkStats();
        $metrics->network_in_bytes = $network['in'] ?? 0;
        $metrics->network_out_bytes = $network['out'] ?? 0;

        // Database stats
        try {
            $dbStats = $this->getDatabaseStats();
            $metrics->database_connections = $dbStats['connections'];
            $metrics->database_size_mb = $dbStats['size_mb'];
        } catch (\Exception $e) {
            \Log::warning('Could not retrieve database stats: ' . $e->getMessage());
        }

        // Cache stats
        try {
            $metrics->cache_memory_usage_mb = $this->getCacheStats();
        } catch (\Exception $e) {
            \Log::warning('Could not retrieve cache stats: ' . $e->getMessage());
        }

        // Session stats
        $metrics->active_sessions = $this->getSessionCount();

        // Queue stats
        $queueStats = $this->getQueueStats();
        $metrics->pending_jobs = $queueStats['pending'];
        $metrics->failed_jobs = $queueStats['failed'];

        $metrics->timestamp = now();

        $metrics->save();

        return $metrics;
    }

    /**
     * Get CPU usage percentage
     */
    private function getCpuUsage(): float
    {
        // On Windows, try to use tasklist; on Linux, use top
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows estimation
            $cpuUsage = round(rand(5, 45), 2); // Placeholder
        } else {
            // Linux: use /proc/stat
            $load = sys_getloadavg();
            $cpuUsage = min(100, round(($load[0] * 100) / shell_exec('nproc'), 2));
        }

        return max(0, min(100, $cpuUsage));
    }

    /**
     * Get memory usage info
     */
    private function getMemoryInfo(): array
    {
        $total = memory_get_peak_usage(true);
        $used = memory_get_usage(true);

        // On Linux, try to get system memory from /proc/meminfo
        if (file_exists('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $matches);
            if (isset($matches[1])) {
                $total = intval($matches[1]) * 1024;
            }
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $matches);
            if (isset($matches[1])) {
                $available = intval($matches[1]) * 1024;
                $used = $total - $available;
            }
        }

        return [
            'total' => $total,
            'used' => $used,
            'free' => $total - $used,
        ];
    }

    /**
     * Get disk usage info
     */
    private function getDiskInfo(): array
    {
        $diskPath = storage_path();
        
        return [
            'total' => disk_total_space($diskPath),
            'used' => disk_total_space($diskPath) - disk_free_space($diskPath),
            'free' => disk_free_space($diskPath),
        ];
    }

    /**
     * Get load average
     */
    private function getLoadAverage(): array
    {
        if (function_exists('sys_getloadavg')) {
            return sys_getloadavg();
        }
        return [0, 0, 0];
    }

    /**
     * Get process count
     */
    private function getProcessCount(): int
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = shell_exec('tasklist /v | find /c /v ""');
            return intval(trim($output)) - 1;
        } else {
            $output = shell_exec('ps aux | wc -l');
            return intval(trim($output)) - 1;
        }
    }

    /**
     * Get network statistics
     */
    private function getNetworkStats(): array
    {
        $stats = ['in' => 0, 'out' => 0];

        // This is a simplified version; real implementation would read from /proc/net/dev
        if (file_exists('/proc/net/dev')) {
            $content = file_get_contents('/proc/net/dev');
            // Parse network stats (implementation varies by system)
        }

        return $stats;
    }

    /**
     * Get database statistics
     */
    private function getDatabaseStats(): array
    {
        $database = config('database.connections.mysql.database');
        $size = 0;

        // Get database size
        try {
            $result = DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.tables WHERE table_schema = ?", [$database]);
            $size = $result[0]->size ?? 0;
        } catch (\Exception $e) {
            \Log::warning('Could not calculate database size: ' . $e->getMessage());
        }

        // Get active connections
        $connections = 0;
        try {
            $result = DB::select("SHOW PROCESSLIST");
            $connections = count($result);
        } catch (\Exception $e) {
            // Connection list may not be available
        }

        return [
            'size_mb' => $size,
            'connections' => $connections,
        ];
    }

    /**
     * Get cache statistics
     */
    private function getCacheStats(): float
    {
        // This varies by cache driver; simplified for demonstration
        $cacheUsage = 0;

        if (function_exists('redis_info')) {
            try {
                $redis = new \Redis();
                $redis->connect('127.0.0.1', 6379);
                $info = $redis->info('memory');
                $cacheUsage = ($info['used_memory'] ?? 0) / 1024 / 1024;
            } catch (\Exception $e) {
                // Redis not available
            }
        }

        return $cacheUsage;
    }

    /**
     * Get active session count
     */
    private function getSessionCount(): int
    {
        try {
            return DB::table('sessions')->where('last_activity', '>', now()->subMinutes(15)->timestamp)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get queue statistics
     */
    private function getQueueStats(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            return [
                'pending' => $pending,
                'failed' => $failed,
            ];
        } catch (\Exception $e) {
            return [
                'pending' => 0,
                'failed' => 0,
            ];
        }
    }

    /**
     * Get metrics for a time range
     */
    public function getMetricsRange(int $minutes = 60): array
    {
        $metrics = SystemMetrics::recent($minutes)->get();

        if ($metrics->isEmpty()) {
            return [];
        }

        return [
            'count' => $metrics->count(),
            'start_time' => $metrics->first()->timestamp,
            'end_time' => $metrics->last()->timestamp,
            'cpu' => [
                'min' => $metrics->min('cpu_usage'),
                'max' => $metrics->max('cpu_usage'),
                'avg' => round($metrics->avg('cpu_usage'), 2),
            ],
            'memory' => [
                'min' => $metrics->min('memory_usage'),
                'max' => $metrics->max('memory_usage'),
                'avg' => round($metrics->avg('memory_usage')),
            ],
            'disk' => [
                'min' => $metrics->min('disk_usage'),
                'max' => $metrics->max('disk_usage'),
                'avg' => round($metrics->avg('disk_usage')),
            ],
            'load_average' => [
                'avg_1min' => round($metrics->avg('load_average_1min'), 2),
                'avg_5min' => round($metrics->avg('load_average_5min'), 2),
                'avg_15min' => round($metrics->avg('load_average_15min'), 2),
            ],
        ];
    }

    /**
     * Get latest metrics with health status
     */
    public function getLatestMetrics(): array
    {
        $metrics = SystemMetrics::latest()->first();

        if (!$metrics) {
            return [
                'status' => 'no_data',
                'message' => 'No metrics data available',
            ];
        }

        $cpuPercent = $metrics->cpu_usage;
        $memoryPercent = ($metrics->memory_usage / $metrics->memory_total) * 100;
        $diskPercent = ($metrics->disk_usage / $metrics->disk_total) * 100;

        return [
            'timestamp' => $metrics->timestamp,
            'health_status' => $metrics->getHealthStatus(),
            'is_under_stress' => $metrics->isUnderStress(),
            'cpu' => [
                'usage' => round($cpuPercent, 2),
                'status' => $cpuPercent > 80 ? 'critical' : ($cpuPercent > 60 ? 'warning' : 'healthy'),
            ],
            'memory' => [
                'usage_mb' => round($metrics->memory_usage / 1024 / 1024, 2),
                'total_mb' => round($metrics->memory_total / 1024 / 1024, 2),
                'percent' => round($memoryPercent, 2),
                'status' => $memoryPercent > 85 ? 'critical' : ($memoryPercent > 70 ? 'warning' : 'healthy'),
            ],
            'disk' => [
                'usage_mb' => round($metrics->disk_usage / 1024 / 1024, 2),
                'total_mb' => round($metrics->disk_total / 1024 / 1024, 2),
                'free_mb' => round($metrics->disk_free / 1024 / 1024, 2),
                'percent' => round($diskPercent, 2),
                'status' => $diskPercent > 90 ? 'critical' : ($diskPercent > 75 ? 'warning' : 'healthy'),
            ],
            'load_average' => [
                '1min' => round($metrics->load_average_1min, 2),
                '5min' => round($metrics->load_average_5min, 2),
                '15min' => round($metrics->load_average_15min, 2),
            ],
            'database' => [
                'connections' => $metrics->database_connections,
                'size_mb' => round($metrics->database_size_mb, 2),
            ],
            'sessions' => $metrics->active_sessions,
            'queue' => [
                'pending' => $metrics->pending_jobs,
                'failed' => $metrics->failed_jobs,
            ],
        ];
    }
}
