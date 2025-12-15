<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:database {--verify : Verify backup after creation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a database backup';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupService = new BackupService();
        $this->info('Starting database backup...');

        try {
            $backup = $backupService->backup('manual', auth()->id());

            if ($backup) {
                $this->info("✓ Backup created successfully!");
                $this->line("  File: {$backup->filename}");
                $this->line("  Size: " . round($backup->size / 1024 / 1024, 2) . " MB");
                $this->line("  Path: {$backup->path}");

                if ($this->option('verify')) {
                    $this->info('Verifying backup...');
                    if ($backupService->verifyBackup($backup)) {
                        $this->info('✓ Backup verified successfully!');
                    } else {
                        $this->warning('⚠ Backup verification failed. Please check the backup file.');
                    }
                }

                return 0;
            } else {
                $this->error('✗ Backup creation failed');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            return 1;
        }
    }
}
