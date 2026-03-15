<?php

namespace App\Console\Commands;

use App\Services\ChatbotAnalyticsService;
use Illuminate\Console\Command;

/**
 * Artisan command: analytics:clean
 *
 * Deletes chatbot analytics rows older than the specified number of days.
 * Designed to be run via Laravel's scheduler (daily).
 *
 * Usage:
 *   php artisan analytics:clean           # defaults to 90 days
 *   php artisan analytics:clean --days=30
 */
class CleanupAnalytics extends Command
{
    protected $signature = 'analytics:clean {--days=90 : Number of days to retain}';

    protected $description = 'Remove chatbot analytics records older than N days';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('Days must be a positive integer.');
            return self::FAILURE;
        }

        $this->info("Cleaning chatbot analytics older than {$days} days...");

        try {
            $analyticsService = app(ChatbotAnalyticsService::class);
            $result = $analyticsService->cleanup($days);

            $this->info('Cleanup complete:');
            $this->line("  Analytics deleted:       " . ($result['analytics_deleted'] ?? 0));
            $this->line("  Rate limits deleted:     " . ($result['rate_limits_deleted'] ?? 0));
            $this->line("  Conversations archived:  " . ($result['conversations_archived'] ?? 0));

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Cleanup failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
