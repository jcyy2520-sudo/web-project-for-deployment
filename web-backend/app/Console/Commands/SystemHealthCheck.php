<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SystemHealthCheck extends Command
{
    protected $signature = 'system:health-check 
                            {--json : Output as JSON}
                            {--alert : Send alerts for critical issues}';

    protected $description = 'Run comprehensive system health checks';

    public function handle(): int
    {
        $this->info('Running system health checks...');
        
        $results = [
            'timestamp' => now()->toISOString(),
            'status' => 'healthy',
            'checks' => [],
        ];

        // 1. Database Check
        $results['checks']['database'] = $this->checkDatabase();

        // 2. Cache Check
        $results['checks']['cache'] = $this->checkCache();

        // 3. Disk Space Check
        $results['checks']['disk'] = $this->checkDiskSpace();

        // 4. Queue Check
        $results['checks']['queue'] = $this->checkQueue();

        // 5. Backup Check
        $results['checks']['backups'] = $this->checkBackups();

        // 6. Configuration Check
        $results['checks']['configuration'] = $this->checkConfiguration();

        // 7. Log Size Check
        $results['checks']['logs'] = $this->checkLogs();

        // Determine overall status
        foreach ($results['checks'] as $check) {
            if ($check['status'] === 'critical') {
                $results['status'] = 'critical';
                break;
            }
            if ($check['status'] === 'warning' && $results['status'] !== 'critical') {
                $results['status'] = 'warning';
            }
        }

        // Output results
        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            $this->displayResults($results);
        }

        // Alert if critical
        if ($this->option('alert') && $results['status'] === 'critical') {
            $this->sendAlerts($results);
        }

        // Log results
        Log::channel('security')->info('System health check completed', $results);

        return $results['status'] === 'healthy' ? 0 : 1;
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            // Check for table locks or slow queries
            $processCount = DB::select("SHOW PROCESSLIST");
            $slowQueries = collect($processCount)->filter(fn($p) => $p->Time > 30)->count();

            return [
                'status' => $latency > 500 ? 'warning' : 'healthy',
                'latency_ms' => $latency,
                'database' => DB::connection()->getDatabaseName(),
                'active_connections' => count($processCount),
                'slow_queries' => $slowQueries,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . now()->timestamp;
            Cache::put($key, 'test', 10);
            $result = Cache::get($key);
            Cache::forget($key);

            return [
                'status' => $result === 'test' ? 'healthy' : 'warning',
                'driver' => config('cache.default'),
                'working' => $result === 'test',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkDiskSpace(): array
    {
        $storagePath = storage_path();
        $diskFree = disk_free_space($storagePath);
        $diskTotal = disk_total_space($storagePath);
        $usedPercent = round((($diskTotal - $diskFree) / $diskTotal) * 100, 2);

        $status = 'healthy';
        if ($usedPercent > 90) {
            $status = 'critical';
        } elseif ($usedPercent > 80) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'used_percent' => $usedPercent,
            'free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
            'total_gb' => round($diskTotal / 1024 / 1024 / 1024, 2),
        ];
    }

    private function checkQueue(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            
            // Check for old jobs (stuck)
            $oldJobs = DB::table('jobs')
                ->where('created_at', '<', now()->subHours(1))
                ->count();

            $status = 'healthy';
            if ($failed > 100 || $oldJobs > 50) {
                $status = 'warning';
            }
            if ($failed > 500 || $oldJobs > 200) {
                $status = 'critical';
            }

            return [
                'status' => $status,
                'pending_jobs' => $pending,
                'failed_jobs' => $failed,
                'stuck_jobs' => $oldJobs,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'error' => 'Queue tables not available',
            ];
        }
    }

    private function checkBackups(): array
    {
        try {
            $latestBackup = DatabaseBackup::where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->first();

            if (!$latestBackup) {
                return [
                    'status' => 'critical',
                    'message' => 'No completed backups found',
                    'last_backup' => null,
                ];
            }

            $hoursSinceBackup = $latestBackup->completed_at->diffInHours(now());
            
            $status = 'healthy';
            if ($hoursSinceBackup > 48) {
                $status = 'critical';
            } elseif ($hoursSinceBackup > 24) {
                $status = 'warning';
            }

            return [
                'status' => $status,
                'last_backup' => $latestBackup->completed_at->toISOString(),
                'hours_since_backup' => $hoursSinceBackup,
                'backup_size' => $latestBackup->formatted_size ?? 'Unknown',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkConfiguration(): array
    {
        $issues = [];

        if (config('app.debug') === true) {
            $issues[] = 'APP_DEBUG is enabled';
        }

        if (config('app.env') === 'local') {
            $issues[] = 'APP_ENV is set to local';
        }

        if (empty(config('app.key'))) {
            $issues[] = 'APP_KEY is not set';
        }

        $status = 'healthy';
        if (count($issues) > 0) {
            $status = config('app.env') === 'production' ? 'critical' : 'warning';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'environment' => config('app.env'),
        ];
    }

    private function checkLogs(): array
    {
        $logPath = storage_path('logs');
        $totalSize = 0;
        $largeFiles = [];

        if (is_dir($logPath)) {
            foreach (new \DirectoryIterator($logPath) as $file) {
                if ($file->isFile()) {
                    $size = $file->getSize();
                    $totalSize += $size;
                    
                    // Files larger than 100MB
                    if ($size > 100 * 1024 * 1024) {
                        $largeFiles[] = [
                            'name' => $file->getFilename(),
                            'size_mb' => round($size / 1024 / 1024, 2),
                        ];
                    }
                }
            }
        }

        $status = 'healthy';
        if (count($largeFiles) > 0) {
            $status = 'warning';
        }
        if ($totalSize > 1024 * 1024 * 1024) { // > 1GB total
            $status = 'warning';
        }

        return [
            'status' => $status,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'large_files' => $largeFiles,
        ];
    }

    private function displayResults(array $results): void
    {
        $statusColors = [
            'healthy' => 'green',
            'warning' => 'yellow',
            'critical' => 'red',
        ];

        $this->newLine();
        $this->line("Overall Status: <fg={$statusColors[$results['status']]}>{$results['status']}</>");
        $this->newLine();

        foreach ($results['checks'] as $name => $check) {
            $color = $statusColors[$check['status']] ?? 'white';
            $icon = $check['status'] === 'healthy' ? '✓' : ($check['status'] === 'warning' ? '⚠' : '✗');
            
            $this->line("<fg={$color}>{$icon}</> {$name}: <fg={$color}>{$check['status']}</>");
            
            if (isset($check['error'])) {
                $this->line("   Error: {$check['error']}");
            }
            if (isset($check['issues']) && count($check['issues']) > 0) {
                foreach ($check['issues'] as $issue) {
                    $this->line("   - {$issue}");
                }
            }
        }
        
        $this->newLine();
    }

    private function sendAlerts(array $results): void
    {
        Log::channel('security')->critical('System health check failed', $results);
        
        // TODO: Implement actual alerting (email, Slack, etc.)
        $this->warn('ALERT: Critical health check issues detected!');
    }
}
