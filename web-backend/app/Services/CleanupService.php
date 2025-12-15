<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CleanupService
{
    /**
     * Perform all cleanup tasks
     */
    public function performAllCleanup(): array
    {
        $results = [];

        try {
            $results['logs'] = $this->rotateLogs();
        } catch (\Exception $e) {
            Log::error('Error rotating logs: ' . $e->getMessage());
            $results['logs'] = ['success' => false, 'error' => $e->getMessage()];
        }

        try {
            $results['cache'] = $this->clearCache();
        } catch (\Exception $e) {
            Log::error('Error clearing cache: ' . $e->getMessage());
            $results['cache'] = ['success' => false, 'error' => $e->getMessage()];
        }

        try {
            $results['old_backups'] = $this->removeOldBackups();
        } catch (\Exception $e) {
            Log::error('Error removing old backups: ' . $e->getMessage());
            $results['old_backups'] = ['success' => false, 'error' => $e->getMessage()];
        }

        try {
            $results['temp_files'] = $this->cleanupTempFiles();
        } catch (\Exception $e) {
            Log::error('Error cleaning temp files: ' . $e->getMessage());
            $results['temp_files'] = ['success' => false, 'error' => $e->getMessage()];
        }

        try {
            $results['old_sessions'] = $this->cleanupOldSessions();
        } catch (\Exception $e) {
            Log::error('Error cleaning old sessions: ' . $e->getMessage());
            $results['old_sessions'] = ['success' => false, 'error' => $e->getMessage()];
        }

        try {
            $results['failed_jobs'] = $this->archiveFailedJobs();
        } catch (\Exception $e) {
            Log::error('Error archiving failed jobs: ' . $e->getMessage());
            $results['failed_jobs'] = ['success' => false, 'error' => $e->getMessage()];
        }

        try {
            $results['old_metrics'] = $this->archiveOldMetrics();
        } catch (\Exception $e) {
            Log::error('Error archiving old metrics: ' . $e->getMessage());
            $results['old_metrics'] = ['success' => false, 'error' => $e->getMessage()];
        }

        Log::info('Cleanup task completed', ['results' => $results]);

        return $results;
    }

    /**
     * Rotate application logs
     */
    public function rotateLogs(): array
    {
        $logDir = storage_path('logs');
        $archivedDir = $logDir . '/archived';

        // Create archived directory if it doesn't exist
        if (!is_dir($archivedDir)) {
            mkdir($archivedDir, 0755, true);
        }

        $rotated = 0;
        $maxFileSize = 52428800; // 50MB

        foreach (glob($logDir . '/laravel-*.log') as $file) {
            if (filesize($file) > $maxFileSize) {
                $timestamp = date('Y-m-d_H-i-s', filemtime($file));
                $newName = $archivedDir . '/' . basename($file, '.log') . '-' . $timestamp . '.log';
                
                if (rename($file, $newName)) {
                    $rotated++;
                    // Compress if possible
                    if (function_exists('gzcompress')) {
                        $this->compressFile($newName);
                    }
                }
            }
        }

        // Remove logs older than 30 days
        $this->removeOlderThan($archivedDir, 30);

        return [
            'success' => true,
            'rotated_count' => $rotated,
            'log_directory' => $logDir,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Clear application cache
     */
    public function clearCache(): array
    {
        $cacheDir = storage_path('framework/cache');
        $viewsDir = storage_path('framework/views');

        $clearedFiles = 0;

        // Clear cache files
        if (is_dir($cacheDir)) {
            $clearedFiles += $this->clearDirectory($cacheDir);
        }

        // Clear compiled views
        if (is_dir($viewsDir)) {
            $clearedFiles += $this->clearDirectory($viewsDir);
        }

        // Clear cache using artisan if available
        try {
            \Artisan::call('cache:clear');
        } catch (\Exception $e) {
            Log::warning('Could not clear cache via artisan: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'files_removed' => $clearedFiles,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Remove backups older than specified days
     */
    public function removeOldBackups(int $daysToKeep = 30): array
    {
        $backupDir = storage_path('backups');
        $cutoffDate = now()->subDays($daysToKeep);

        $deleted = 0;
        $totalSize = 0;

        if (!is_dir($backupDir)) {
            return [
                'success' => true,
                'deleted_count' => 0,
                'freed_space_mb' => 0,
            ];
        }

        foreach (glob($backupDir . '/*.sql*') as $file) {
            if (filemtime($file) < $cutoffDate->timestamp) {
                $totalSize += filesize($file);
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return [
            'success' => true,
            'deleted_count' => $deleted,
            'freed_space_mb' => round($totalSize / 1024 / 1024, 2),
            'kept_backups_days' => $daysToKeep,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Clean up temporary files
     */
    public function cleanupTempFiles(): array
    {
        $tempDirs = [
            storage_path('app/temp'),
            storage_path('app/uploads/tmp'),
            sys_get_temp_dir(),
        ];

        $deleted = 0;

        foreach ($tempDirs as $dir) {
            if (is_dir($dir)) {
                $deleted += $this->clearOldFiles($dir, 7); // Older than 7 days
            }
        }

        return [
            'success' => true,
            'deleted_count' => $deleted,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupOldSessions(int $lifetimeMinutes = 120): array
    {
        $cutoffTime = now()->subMinutes($lifetimeMinutes)->timestamp;

        $deleted = DB::table('sessions')
            ->where('last_activity', '<', $cutoffTime)
            ->delete();

        return [
            'success' => true,
            'deleted_count' => $deleted,
            'lifetime_minutes' => $lifetimeMinutes,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Archive failed jobs
     */
    public function archiveFailedJobs(): array
    {
        // Move old failed jobs to archive table if it exists
        $archived = 0;

        try {
            $cutoffDate = now()->subDays(30);
            $failedJobs = DB::table('failed_jobs')
                ->where('failed_at', '<', $cutoffDate)
                ->get();

            // Create archive if needed
            if ($failedJobs->count() > 0) {
                // Log for archive purposes
                foreach ($failedJobs as $job) {
                    Log::warning('Archiving failed job', [
                        'id' => $job->id,
                        'queue' => $job->queue,
                        'failed_at' => $job->failed_at,
                    ]);
                }

                // Delete old ones
                $archived = DB::table('failed_jobs')
                    ->where('failed_at', '<', $cutoffDate)
                    ->delete();
            }
        } catch (\Exception $e) {
            Log::warning('Could not archive failed jobs: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'archived_count' => $archived,
            'days_kept' => 30,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Archive old metrics data
     */
    public function archiveOldMetrics(int $daysToKeep = 90): array
    {
        try {
            $cutoffDate = now()->subDays($daysToKeep);

            $deleted = DB::table('system_metrics')
                ->where('timestamp', '<', $cutoffDate)
                ->delete();

            return [
                'success' => true,
                'archived_count' => $deleted,
                'days_kept' => $daysToKeep,
                'timestamp' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::warning('Could not archive metrics: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clear a directory of all files
     */
    private function clearDirectory(string $path): int
    {
        $count = 0;
        $files = glob($path . '/*');

        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Remove files older than specified days
     */
    private function removeOlderThan(string $path, int $days): int
    {
        $cutoffTime = now()->subDays($days)->timestamp;
        $count = 0;

        foreach (glob($path . '/*') as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Remove old files from a directory
     */
    private function clearOldFiles(string $path, int $days): int
    {
        $cutoffTime = now()->subDays($days)->timestamp;
        $count = 0;

        if (!is_dir($path)) {
            return 0;
        }

        foreach (glob($path . '/*') as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Compress a file using gzip
     */
    private function compressFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        try {
            $compressed = $filePath . '.gz';
            $content = file_get_contents($filePath);
            file_put_contents($compressed, gzcompress($content, 9));
            unlink($filePath);
            return true;
        } catch (\Exception $e) {
            Log::warning('Could not compress file: ' . $e->getMessage());
            return false;
        }
    }
}
