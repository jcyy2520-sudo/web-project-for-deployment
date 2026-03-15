<?php

namespace App\Console\Commands;

use App\Models\ActionLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ArchiveInactiveUsers extends Command
{
    protected $signature = 'users:archive-inactive {--days=30 : Days of inactivity before archiving} {--dry-run : Show which users would be archived without actually archiving}';

    protected $description = 'Archive (soft-delete) users who have had no activity for 30+ consecutive days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $this->info("Looking for users inactive since {$cutoff->format('Y-m-d H:i:s')} ({$days} days)...");

        // Only archive client accounts — never auto-archive admin/staff/cashier
        $inactiveUsers = User::where('role', 'client')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('account_status', 'active')
                  ->orWhereNull('account_status');
            })
            ->where(function ($query) use ($cutoff) {
                $query->where('last_activity_at', '<', $cutoff)
                      ->orWhere(function ($q) use ($cutoff) {
                          // Users who never had activity tracked — fall back to created_at
                          $q->whereNull('last_activity_at')
                            ->where('created_at', '<', $cutoff);
                      });
            })
            ->get();

        if ($inactiveUsers->isEmpty()) {
            $this->info('No inactive users found.');
            return self::SUCCESS;
        }

        $this->info("Found {$inactiveUsers->count()} inactive user(s).");

        if ($dryRun) {
            $this->table(
                ['ID', 'Name', 'Email', 'Last Activity', 'Created At'],
                $inactiveUsers->map(fn ($u) => [
                    $u->id,
                    $u->first_name . ' ' . $u->last_name,
                    $u->email,
                    $u->last_activity_at?->format('Y-m-d H:i') ?? 'Never',
                    $u->created_at->format('Y-m-d H:i'),
                ])
            );
            $this->warn('Dry run — no users were archived.');
            return self::SUCCESS;
        }

        $archived = 0;
        foreach ($inactiveUsers as $user) {
            $user->account_status = 'archived';
            $user->account_status_reason = "Auto-archived after {$days} days of inactivity";
            $user->is_active = false;
            $user->save();
            $user->delete(); // soft-delete

            try {
                ActionLog::log(
                    'auto_archive',
                    "Auto-archived inactive user: {$user->first_name} {$user->last_name} ({$user->email}) — inactive since " . ($user->last_activity_at?->format('Y-m-d') ?? 'account creation'),
                    'User',
                    $user->id
                );
            } catch (\Exception $e) {
                Log::warning("Failed to log auto-archive action for user {$user->id}: " . $e->getMessage());
            }

            $archived++;
        }

        $this->info("Archived {$archived} inactive user(s).");
        Log::info("Auto-archive: Archived {$archived} users inactive for {$days}+ days.");

        return self::SUCCESS;
    }
}
