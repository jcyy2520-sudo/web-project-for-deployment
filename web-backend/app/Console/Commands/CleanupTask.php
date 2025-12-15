<?php

namespace App\Console\Commands;

use App\Services\CleanupService;
use Illuminate\Console\Command;

class CleanupTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:run {--component=all : Cleanup component to run (all, logs, cache, backups, temp, sessions, jobs, metrics)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run system cleanup tasks (log rotation, cache clearing, old backup removal)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $component = $this->option('component');
        $cleanupService = new CleanupService();

        $this->info('Starting cleanup task...');

        try {
            if ($component === 'all') {
                $results = $cleanupService->performAllCleanup();
            } else {
                $method = 'clean' . ucfirst(str_replace('_', '', $component));
                
                if (!method_exists($cleanupService, $method)) {
                    // Try alternate method names
                    $alternates = [
                        'logs' => 'rotateLogs',
                        'cache' => 'clearCache',
                        'backups' => 'removeOldBackups',
                        'temp' => 'cleanupTempFiles',
                        'sessions' => 'cleanupOldSessions',
                        'jobs' => 'archiveFailedJobs',
                        'metrics' => 'archiveOldMetrics',
                    ];

                    if (isset($alternates[$component])) {
                        $method = $alternates[$component];
                    }
                }

                if (method_exists($cleanupService, $method)) {
                    $results = [$component => $cleanupService->$method()];
                } else {
                    $this->error("Unknown component: {$component}");
                    return 1;
                }
            }

            // Display results
            $this->displayResults($results);

            $this->info('Cleanup task completed successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error('Cleanup task failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display cleanup results
     */
    private function displayResults(array $results): void
    {
        $this->newLine();

        foreach ($results as $component => $result) {
            if (is_array($result)) {
                if ($result['success'] ?? false) {
                    $this->info("✓ {$component}: " . ($result['message'] ?? 'Completed'));
                    
                    // Display additional stats
                    foreach ($result as $key => $value) {
                        if (!in_array($key, ['success', 'message', 'timestamp'])) {
                            $this->line("  {$key}: {$value}");
                        }
                    }
                } else {
                    $this->error("✗ {$component}: " . ($result['error'] ?? 'Failed'));
                }
            }
        }

        $this->newLine();
    }
}
