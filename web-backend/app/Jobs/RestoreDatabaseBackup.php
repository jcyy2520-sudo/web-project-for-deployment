<?php

namespace App\Jobs;

use App\Models\DatabaseBackup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\BackupService;

class RestoreDatabaseBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $backup;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(DatabaseBackup $backup, int $userId)
    {
        $this->backup = $backup;
        $this->userId = $userId;
        
        // Set queue to priority to run quickly
        $this->queue = 'backups';
        $this->timeout = 3600; // 1 hour timeout for large backups
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting database restore from backup: {$this->backup->filename}", [
                'backup_id' => $this->backup->id,
                'initiated_by' => $this->userId,
            ]);

            $this->backup->update([
                'status' => 'restoring',
                'restore_started_at' => now(),
            ]);

            $backupService = app(BackupService::class);
            $success = $backupService->restore($this->backup);

            if ($success) {
                $this->backup->update([
                    'status' => 'restored',
                    'restore_completed_at' => now(),
                    'restored_by' => $this->userId,
                ]);

                Log::info("Database restore completed successfully", [
                    'backup_id' => $this->backup->id,
                    'filename' => $this->backup->filename,
                ]);

                // You could send notification here
                // Notification::send($user, new BackupRestoredNotification($this->backup));
            } else {
                $this->backup->update([
                    'status' => 'restore_failed',
                    'restore_completed_at' => now(),
                ]);

                Log::error("Database restore failed", [
                    'backup_id' => $this->backup->id,
                    'filename' => $this->backup->filename,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Backup restore job exception: " . $e->getMessage(), [
                'backup_id' => $this->backup->id,
                'exception' => $e,
            ]);

            $this->backup->update([
                'status' => 'restore_failed',
                'error_message' => $e->getMessage(),
                'restore_completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Backup restore job failed permanently', [
            'backup_id' => $this->backup->id,
            'exception' => $exception->getMessage(),
        ]);

        $this->backup->update([
            'status' => 'restore_failed',
            'error_message' => 'Job failed: ' . $exception->getMessage(),
        ]);
    }
}
