<?php

namespace App\Console\Commands;

use App\Services\SystemMetricsService;
use Illuminate\Console\Command;

class CollectMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:collect';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect system performance metrics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $metricsService = new SystemMetricsService();
            $metrics = $metricsService->collectMetrics();

            $this->info('✓ Metrics collected successfully!');
            $this->line("CPU Usage: {$metrics->cpu_usage}%");
            $this->line("Memory: " . round($metrics->memory_usage / 1024 / 1024, 2) . " MB / " . round($metrics->memory_total / 1024 / 1024, 2) . " MB");
            $this->line("Disk: " . round($metrics->disk_usage / 1024 / 1024 / 1024, 2) . " GB / " . round($metrics->disk_total / 1024 / 1024 / 1024, 2) . " GB");
            $this->line("Health Status: {$metrics->getHealthStatus()}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Metrics collection failed: ' . $e->getMessage());
            return 1;
        }
    }
}
