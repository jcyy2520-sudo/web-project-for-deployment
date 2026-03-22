<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Collect system metrics every 5 minutes
        $schedule->command('metrics:collect')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onSuccess(fn () => \Illuminate\Support\Facades\Log::info('Metrics collected'))
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Metrics collection failed'));

        // Run system health check every 15 minutes
        $schedule->command('system:health-check --alert')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::channel('security')->critical('System health check failed'));

        // Rotate logs daily at 1 AM
        $schedule->command('cleanup:run --component=logs')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->onSuccess(fn () => \Illuminate\Support\Facades\Log::info('Logs rotated'))
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Log rotation failed'));

        // Clear cache daily at 2 AM
        $schedule->command('cleanup:run --component=cache')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->onSuccess(fn () => \Illuminate\Support\Facades\Log::info('Cache cleared'))
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Cache clearing failed'));

        // Backup database daily at 3 AM
        $schedule->command('backup:database')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->onSuccess(fn () => \Illuminate\Support\Facades\Log::info('Database backed up'))
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Database backup failed'));

        // Clean old sessions every hour
        $schedule->command('cleanup:run --component=sessions')
            ->hourly()
            ->withoutOverlapping()
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::warning('Session cleanup failed'));

        // Clean temporary files daily at 4 AM
        $schedule->command('cleanup:run --component=temp')
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->onSuccess(fn () => \Illuminate\Support\Facades\Log::info('Temp files cleaned'))
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Temp file cleanup failed'));

        // Archive old backups weekly on Sunday at 5 AM
        $schedule->command('cleanup:run --component=backups')
            ->weekly()
            ->sundays()
            ->at('05:00')
            ->withoutOverlapping()
            ->onSuccess(fn () => \Illuminate\Support\Facades\Log::info('Old backups archived'))
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Backup archival failed'));

        // Archive old metrics weekly on Sunday at 6 AM
        $schedule->command('cleanup:run --component=metrics')
            ->weekly()
            ->sundays()
            ->at('06:00')
            ->withoutOverlapping()
            ->onSuccess(fn () => \Illuminate\Support\Facades\Log::info('Old metrics archived'))
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Metrics archival failed'));

        // Archive failed jobs daily at 7 AM
        $schedule->command('cleanup:run --component=jobs')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->onSuccess(fn () => \Illuminate\Support\Facades\Log::info('Failed jobs archived'))
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Failed job archival failed'));

        // Clean chatbot analytics older than 90 days — daily at 4:30 AM
        $schedule->command('analytics:clean --days=90')
            ->dailyAt('04:30')
            ->withoutOverlapping()
            ->onSuccess(fn () => \Illuminate\Support\Facades\Log::info('Chatbot analytics cleaned'))
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::warning('Chatbot analytics cleanup failed'));

        // ML model retraining — weekly on Monday at 2:30 AM
        $schedule->command('ml:retrain')
            ->weekly()
            ->mondays()
            ->at('02:30')
            ->withoutOverlapping()
            ->onSuccess(fn () => \Illuminate\Support\Facades\Log::info('ML model retrained successfully'))
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::warning('ML model retraining failed'));

        // Auto-archive inactive users — daily at 3:30 AM
        $schedule->command('users:archive-inactive')
            ->dailyAt('03:30')
            ->withoutOverlapping()
            ->onSuccess(fn () => \Illuminate\Support\Facades\Log::info('Inactive users archival completed'))
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Inactive users archival failed'));

        // Auto-archive terminal appointments (completed/cancelled/declined/no_show) after 24 hours
        $schedule->command('appointments:auto-archive --hours=24')
            ->hourly()
            ->withoutOverlapping()
            ->onSuccess(fn () => \Illuminate\Support\Facades\Log::info('Terminal appointments auto-archived'))
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Terminal appointments auto-archive failed'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
