<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    /**
     * Get comprehensive system health status
     */
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'application' => $this->checkApplication(),
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
            'system' => $this->checkSystem(),
        ];

        // Determine overall status
        $status = 'healthy';
        foreach ($health as $key => $value) {
            if (is_array($value) && isset($value['status']) && $value['status'] === 'unhealthy') {
                $status = 'degraded';
            }
            if (is_array($value) && isset($value['status']) && $value['status'] === 'critical') {
                $status = 'critical';
            }
        }

        $health['status'] = $status;

        $statusCode = $status === 'critical' ? 503 : 200;
        return response()->json($health, $statusCode);
    }

    /**
     * Check application configuration
     */
    private function checkApplication(): array
    {
        return [
            'status' => 'healthy',
            'name' => config('app.name'),
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
            'url' => config('app.url'),
        ];
    }

    /**
     * Check database connection
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            
            // Try a simple query
            DB::select('SELECT 1');

            return [
                'status' => 'healthy',
                'connection' => config('database.default'),
                'database' => config('database.connections.' . config('database.default') . '.database'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'connection' => config('database.default'),
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
            ];
        }
    }

    /**
     * Check cache system
     */
    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . time();
            Cache::put($testKey, 'test', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            if ($value === 'test') {
                return [
                    'status' => 'healthy',
                    'driver' => config('cache.default'),
                ];
            }

            return [
                'status' => 'unhealthy',
                'driver' => config('cache.default'),
                'error' => 'Cache write/read failed',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'driver' => config('cache.default'),
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
            ];
        }
    }

    /**
     * Check storage system
     */
    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk('local');
            $storagePath = storage_path();

            // Check if storage is writable
            $testFile = '.health_check_' . time();
            $disk->put($testFile, 'test');
            $disk->delete($testFile);

            // Get disk usage information
            $diskUsage = disk_free_space($storagePath);
            $diskTotal = disk_total_space($storagePath);
            $usagePercent = $diskTotal > 0 ? (($diskTotal - $diskUsage) / $diskTotal) * 100 : 0;

            $status = $usagePercent > 90 ? 'critical' : ($usagePercent > 75 ? 'unhealthy' : 'healthy');

            return [
                'status' => $status,
                'writable' => true,
                'free_space' => $this->formatBytes($diskUsage),
                'total_space' => $this->formatBytes($diskTotal),
                'usage_percent' => round($usagePercent, 2),
                'storage_path' => $storagePath,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'writable' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
            ];
        }
    }

    /**
     * Check queue system
     */
    private function checkQueue(): array
    {
        try {
            $driver = config('queue.default');

            if ($driver === 'sync') {
                return [
                    'status' => 'healthy',
                    'driver' => $driver,
                    'note' => 'Using synchronous queue driver',
                ];
            }

            // For database queue, check if jobs table exists
            if ($driver === 'database') {
                try {
                    $jobsCount = DB::table(config('queue.connections.database.table', 'jobs'))->count();
                    return [
                        'status' => 'healthy',
                        'driver' => $driver,
                        'pending_jobs' => $jobsCount,
                    ];
                } catch (\Exception $e) {
                    return [
                        'status' => 'unhealthy',
                        'driver' => $driver,
                        'error' => 'Jobs table not accessible',
                    ];
                }
            }

            return [
                'status' => 'healthy',
                'driver' => $driver,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'driver' => config('queue.default'),
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
            ];
        }
    }

    /**
     * Check system resources
     */
    private function checkSystem(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $memoryPercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;

        // Get CPU load (Linux/Unix only)
        $loadAverage = null;
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $loadAverage = round($load[0], 2);
        }

        return [
            'status' => 'healthy',
            'php_version' => phpversion(),
            'memory_usage' => $this->formatBytes($memoryUsage),
            'memory_limit' => ini_get('memory_limit'),
            'memory_percent' => round($memoryPercent, 2),
            'load_average' => $loadAverage,
            'uptime' => $this->getServerUptime(),
        ];
    }

    /**
     * Parse memory limit from PHP config
     */
    private function parseMemoryLimit($limit): int
    {
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $value = (int) $limit;
        $unit = strtoupper(substr($limit, -1));

        return match ($unit) {
            'K' => $value * 1024,
            'M' => $value * 1024 * 1024,
            'G' => $value * 1024 * 1024 * 1024,
            default => $value,
        };
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Get server uptime (Linux/Unix only)
     */
    private function getServerUptime(): ?string
    {
        if (@file_exists('/proc/uptime')) {
            $uptime = (int) file_get_contents('/proc/uptime');
            $days = intdiv($uptime, 86400);
            $hours = intdiv($uptime % 86400, 3600);
            $minutes = intdiv($uptime % 3600, 60);
            
            return "{$days}d {$hours}h {$minutes}m";
        }

        return null;
    }
}
