<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| These tasks run automatically via Laravel's scheduler.
| Ensure the scheduler is running: php artisan schedule:work
|
*/

// Sync chatbot knowledge every 4 hours to stay up-to-date
Schedule::command('chatbot:sync-knowledge --skip-embeddings')
    ->everyFourHours()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/chatbot-sync.log'));

// Full knowledge rebuild with embeddings once daily at 3 AM
Schedule::command('chatbot:sync-knowledge')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/chatbot-sync.log'));

// Cleanup old chatbot analytics data (older than 90 days)
Schedule::call(function () {
    \App\Models\ChatbotAnalytics::where('created_at', '<', now()->subDays(90))->delete();
    \Illuminate\Support\Facades\Log::info('Chatbot analytics cleanup completed');
})->name('chatbot-analytics-cleanup')->weekly()->withoutOverlapping();
