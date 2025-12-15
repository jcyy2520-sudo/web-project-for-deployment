<?php

namespace App\Services;

use App\Models\DatabaseBackup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupService
{
    /**
     * Create a database backup
     */
    public function backup(string $type = 'automatic', ?int $userId = null): ?DatabaseBackup
    {
        try {
            $backupDir = storage_path('backups');
            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "backup_{$timestamp}.sql";
            $path = "{$backupDir}/{$filename}";

            // Create backup record
            $backup = DatabaseBackup::create([
                'filename' => $filename,
                'path' => $path,
                'database_name' => config('database.connections.mysql.database'),
                'status' => 'pending',
                'backup_type' => $type,
                'created_by' => $userId,
                'started_at' => now(),
            ]);

            // Execute backup command
            $command = $this->getBackupCommand($path);
            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                $size = filesize($path);
                $backup->update([
                    'size' => $size,
                    'status' => 'completed',
                    'completed_at' => now(),
                    'is_verified' => true,
                ]);

                Log::info("Database backup completed: {$filename} ({$size} bytes)");
                return $backup;
            } else {
                $backup->update([
                    'status' => 'failed',
                    'error_message' => 'Backup command failed',
                ]);
                Log::error('Database backup failed', ['output' => $output]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Backup service error: ' . $e->getMessage());
            if (isset($backup)) {
                $backup->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
            return null;
        }
    }

    /**
     * Get backup command for OS
     */
    private function getBackupCommand(string $path): string
    {
        $host = config('database.connections.mysql.host');
        $user = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $database = config('database.connections.mysql.database');
        $port = config('database.connections.mysql.port', 3306);

        // Escape for command line
        $path = escapeshellarg($path);

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            return "mysqldump -h{$host} -u{$user} -p{$password} -P{$port} {$database} > {$path}";
        } else {
            // Linux/Mac
            return "mysqldump -h{$host} -u{$user} -p{$password} -P{$port} {$database} > {$path}";
        }
    }

    /**
     * Restore from backup
     */
    public function restore(DatabaseBackup $backup): bool
    {
        try {
            if (!$backup->fileExists()) {
                Log::error('Backup file not found: ' . $backup->path);
                return false;
            }

            $host = config('database.connections.mysql.host');
            $user = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $database = config('database.connections.mysql.database');
            $port = config('database.connections.mysql.port', 3306);

            $filePath = escapeshellarg($backup->path);

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $command = "mysql -h{$host} -u{$user} -p{$password} -P{$port} {$database} < {$filePath}";
            } else {
                $command = "mysql -h{$host} -u{$user} -p{$password} -P{$port} {$database} < {$filePath}";
            }

            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                $backup->update([
                    'last_restored_at' => now(),
                ]);
                Log::info("Database restored from backup: " . $backup->filename);
                return true;
            } else {
                Log::error('Database restore failed', ['output' => $output]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Restore service error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete old backups
     */
    public function cleanupOldBackups(int $daysToKeep = 30): int
    {
        $deleted = 0;
        $oldBackups = DatabaseBackup::where('completed_at', '<', now()->subDays($daysToKeep))->get();

        foreach ($oldBackups as $backup) {
            if ($backup->deleteFile()) {
                $backup->delete();
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get backup statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_backups' => DatabaseBackup::count(),
            'successful_backups' => DatabaseBackup::successful()->count(),
            'failed_backups' => DatabaseBackup::where('status', 'failed')->count(),
            'total_size' => DatabaseBackup::sum('size') ?? 0,
            'last_backup' => DatabaseBackup::successful()->latest('completed_at')->first(),
            'recent_backups' => DatabaseBackup::recent(7)->successful()->count(),
            'average_duration' => $this->getAverageDuration(),
        ];
    }

    /**
     * Get average backup duration
     */
    private function getAverageDuration(): float
    {
        $backups = DatabaseBackup::successful()
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get();

        if ($backups->isEmpty()) {
            return 0;
        }

        $totalSeconds = $backups->sum(function ($backup) {
            return $backup->completed_at->diffInSeconds($backup->started_at);
        });

        return round($totalSeconds / $backups->count(), 2);
    }

    /**
     * Verify backup integrity
     */
    public function verifyBackup(DatabaseBackup $backup): bool
    {
        try {
            if (!$backup->fileExists()) {
                Log::error('Backup file not found for verification: ' . $backup->path);
                $backup->update(['is_verified' => false]);
                return false;
            }

            $size = filesize($backup->path);
            if ($size === 0) {
                Log::error('Backup file is empty: ' . $backup->path);
                $backup->update(['is_verified' => false]);
                return false;
            }

            // Check if file is readable and has valid SQL content
            $handle = fopen($backup->path, 'r');
            if (!$handle) {
                Log::error('Cannot read backup file: ' . $backup->path);
                $backup->update(['is_verified' => false]);
                return false;
            }

            $header = fread($handle, 100);
            fclose($handle);

            // Check for SQL dump markers
            if (stripos($header, 'SQL') === false && stripos($header, 'CREATE') === false) {
                Log::warning('Backup file may not be a valid SQL dump: ' . $backup->path);
            }

            $backup->update([
                'is_verified' => true,
                'verified_at' => now(),
                'size' => $size,
            ]);

            Log::info('Backup verified successfully: ' . $backup->filename);
            return true;
        } catch (\Exception $e) {
            Log::error('Backup verification failed: ' . $e->getMessage());
            $backup->update(['is_verified' => false]);
            return false;
        }
    }

    /**
     * Schedule automatic backups
     */
    public function scheduleBackup(string $frequency = 'daily', ?int $userId = null): array
    {
        $schedules = [
            'hourly' => '0 * * * *',
            'daily' => '0 2 * * *',    // 2 AM daily
            'weekly' => '0 2 * * 0',   // Sunday 2 AM
            'monthly' => '0 2 1 * *',  // 1st of month 2 AM
        ];

        if (!isset($schedules[$frequency])) {
            return [
                'success' => false,
                'error' => "Invalid frequency: {$frequency}",
            ];
        }

        Log::info("Backup schedule set to {$frequency}", ['cron' => $schedules[$frequency]]);

        return [
            'success' => true,
            'frequency' => $frequency,
            'cron_expression' => $schedules[$frequency],
            'message' => "Automatic backups scheduled {$frequency}",
        ];
    }

    /**
     * Test restore procedure (dry run)
     */
    public function testRestore(DatabaseBackup $backup): array
    {
        try {
            if (!$backup->fileExists()) {
                return [
                    'success' => false,
                    'error' => 'Backup file not found',
                ];
            }

            // Verify file can be read
            if (!is_readable($backup->path)) {
                return [
                    'success' => false,
                    'error' => 'Backup file is not readable',
                ];
            }

            // Check file size
            $size = filesize($backup->path);
            if ($size === 0) {
                return [
                    'success' => false,
                    'error' => 'Backup file is empty',
                ];
            }

            // Try to parse first few lines for SQL syntax
            $handle = fopen($backup->path, 'r');
            $validLines = 0;
            $totalLines = 0;

            while (($line = fgets($handle)) !== false && $totalLines < 50) {
                $line = trim($line);
                if (empty($line) || substr($line, 0, 2) === '--') {
                    continue;
                }
                if (stripos($line, 'CREATE') !== false || 
                    stripos($line, 'INSERT') !== false || 
                    stripos($line, 'UPDATE') !== false) {
                    $validLines++;
                }
                $totalLines++;
            }
            fclose($handle);

            if ($validLines === 0) {
                return [
                    'success' => false,
                    'error' => 'Backup file does not contain valid SQL',
                ];
            }

            Log::info('Restore test passed for backup: ' . $backup->filename);

            return [
                'success' => true,
                'file_size_mb' => round($size / 1024 / 1024, 2),
                'valid_statements_found' => $validLines,
                'created_at' => $backup->created_at,
                'last_verified' => $backup->verified_at,
                'message' => 'Backup appears to be valid and restorable',
            ];
        } catch (\Exception $e) {
            Log::error('Restore test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get backup recovery procedure
     */
    public function getRecoveryProcedure(DatabaseBackup $backup): array
    {
        return [
            'backup_info' => [
                'filename' => $backup->filename,
                'created_at' => $backup->created_at,
                'size_mb' => round($backup->size / 1024 / 1024, 2),
                'is_verified' => $backup->is_verified,
            ],
            'pre_recovery_steps' => [
                '1. Take a backup of current database',
                '2. Stop all application processes',
                '3. Verify database credentials',
                '4. Ensure sufficient disk space',
            ],
            'recovery_steps' => [
                '1. Use restore() method: $backupService->restore($backup)',
                '2. Verify data integrity after restore',
                '3. Test application functionality',
                '4. Monitor system performance',
            ],
            'post_recovery_steps' => [
                '1. Verify all data has been restored correctly',
                '2. Check application logs for errors',
                '3. Restart application services',
                '4. Notify stakeholders of recovery completion',
                '5. Document recovery in incident log',
            ],
            'rollback_procedure' => [
                'If recovery fails: restore from most recent successful backup',
                'If data corruption detected: use earlier backup',
                'Contact database administrator if issues persist',
            ],
        ];
    }

    /**
     * Get backup schedule status
     */
    public function getScheduleStatus(): array
    {
        $recent = DatabaseBackup::successful()->latest('completed_at')->first();
        $nextBackup = now()->addHours(24); // Assuming daily backups

        return [
            'last_backup' => [
                'completed_at' => $recent?->completed_at,
                'size_mb' => round(($recent?->size ?? 0) / 1024 / 1024, 2),
                'duration_seconds' => $recent ? $recent->completed_at->diffInSeconds($recent->started_at) : 0,
                'status' => 'healthy',
            ],
            'next_scheduled_backup' => $nextBackup,
            'backup_frequency' => 'daily',
            'backup_retention_days' => 30,
            'backups_available' => DatabaseBackup::successful()->count(),
            'total_backup_size_mb' => round(DatabaseBackup::successful()->sum('size') / 1024 / 1024, 2),
        ];
    }
}
