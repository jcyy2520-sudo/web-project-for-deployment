<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchiveTerminalAppointments extends Command
{
    protected $signature = 'appointments:auto-archive {--hours=24 : Hours after status change to archive}';
    protected $description = 'Auto-archive completed, cancelled, declined, and no-show appointments after a configurable delay';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours);

        // For 'completed' appointments, use completed_at (reliable timestamp set on completion)
        $completedCount = Appointment::withoutTrashed()
            ->whereNull('archived_at')
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $cutoff)
            ->update(['archived_at' => now()]);

        // For cancelled/declined/no_show, fall back to updated_at (no dedicated timestamp column)
        $otherCount = Appointment::withoutTrashed()
            ->whereNull('archived_at')
            ->whereIn('status', ['cancelled', 'declined', 'no_show'])
            ->where('updated_at', '<=', $cutoff)
            ->update(['archived_at' => now()]);

        $count = $completedCount + $otherCount;

        if ($count > 0) {
            Log::info("Auto-archived {$count} terminal appointments (older than {$hours}h): {$completedCount} completed, {$otherCount} cancelled/declined/no_show");
            $this->info("Archived {$count} appointments ({$completedCount} completed, {$otherCount} other).");
        } else {
            $this->info('No appointments to archive.');
        }

        return self::SUCCESS;
    }
}
