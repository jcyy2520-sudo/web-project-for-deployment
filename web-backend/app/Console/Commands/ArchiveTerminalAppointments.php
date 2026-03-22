<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ArchiveTerminalAppointments extends Command
{
    protected $signature = 'appointments:auto-archive {--hours=24 : Hours after status change to archive}';
    protected $description = 'Auto-archive completed, cancelled, declined, and no-show appointments after a configurable delay';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours);

        $count = Appointment::whereNull('archived_at')
            ->whereIn('status', ['completed', 'cancelled', 'declined', 'no_show'])
            ->where('updated_at', '<=', $cutoff)
            ->update(['archived_at' => now()]);

        if ($count > 0) {
            Log::info("Auto-archived {$count} terminal appointments (older than {$hours}h)");
            $this->info("Archived {$count} appointments.");
        } else {
            $this->info('No appointments to archive.');
        }

        return self::SUCCESS;
    }
}
