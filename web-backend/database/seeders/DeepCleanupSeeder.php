<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

class DeepCleanupSeeder extends Seeder
{
    /**
     * Comprehensive cleanup:
     * 1. Permanently delete soft-deleted duplicates
     * 2. Merge near-duplicate users (same name, different phone)
     * 3. Remove orphaned appointments
     */
    public function run(): void
    {
        $this->command->info('🧹 Starting comprehensive cleanup...');
        
        // Restore and clean soft-deleted records
        $this->cleanSoftDeletedRecords();
        
        // Merge near-duplicate users
        $this->mergeSimilarUsers();
        
        // Remove orphaned appointments
        $this->removeOrphanedAppointments();
        
        // Clean up test data if user wants (optional)
        $this->command->info('✅ Cleanup completed!');
        $this->command->line('');
        $this->showDatabaseStats();
    }

    private function cleanSoftDeletedRecords(): void
    {
        $this->command->line('Cleaning soft-deleted records...');
        
        // Permanently delete soft-deleted users (except if they have active appointments)
        $softDeletedUsers = User::onlyTrashed()->get();
        $deletedCount = 0;
        
        foreach ($softDeletedUsers as $user) {
            // Check if user has any active appointments
            $activeAppointments = Appointment::where('user_id', $user->id)->count();
            
            if ($activeAppointments === 0) {
                $user->forceDelete();
                $deletedCount++;
            }
        }
        
        if ($deletedCount > 0) {
            $this->command->line("  ✓ Permanently deleted $deletedCount soft-deleted users with no appointments");
        } else {
            $this->command->line("  ✓ No soft-deleted users without active appointments found");
        }
        
        // Permanently delete soft-deleted appointments
        $softDeletedAppointments = Appointment::onlyTrashed()->count();
        if ($softDeletedAppointments > 0) {
            Appointment::onlyTrashed()->forceDelete();
            $this->command->line("  ✓ Permanently deleted $softDeletedAppointments soft-deleted appointments");
        }
    }

    private function mergeSimilarUsers(): void
    {
        $this->command->line('Checking for similar/duplicate users...');
        
        // Find users with same first_name + last_name (potential duplicates)
        $duplicateNames = DB::table('users')
            ->where('role', 'client')
            ->select(DB::raw('CONCAT(first_name, " ", last_name) as full_name'), 
                     DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('CONCAT(first_name, " ", last_name)'))
            ->having('count', '>', 1)
            ->get();
        
        if ($duplicateNames->isEmpty()) {
            $this->command->line("  ✓ No duplicate names found");
            return;
        }

        $mergedCount = 0;
        foreach ($duplicateNames as $duplicate) {
            $users = User::where('role', 'client')
                ->whereRaw("CONCAT(first_name, ' ', last_name) = ?", [$duplicate->full_name])
                ->orderBy('created_at', 'asc')
                ->get();
            
            // Keep first user, merge appointments of others
            $primaryUser = $users[0];
            
            foreach ($users->skip(1) as $secondaryUser) {
                // Reassign all appointments to primary user
                Appointment::where('user_id', $secondaryUser->id)
                    ->update(['user_id' => $primaryUser->id]);
                
                // Delete secondary user
                $secondaryUser->delete();
                $mergedCount++;
            }
            
            $this->command->line("  ✓ Merged duplicate entries for: {$duplicate->full_name}");
        }
        
        if ($mergedCount > 0) {
            $this->command->info("Total duplicate users merged: $mergedCount");
        }
    }

    private function removeOrphanedAppointments(): void
    {
        $this->command->line('Checking for orphaned appointments...');
        
        // Find appointments with deleted users
        $orphaned = Appointment::whereNotIn('user_id', 
            User::select('id')->get()->pluck('id')
        )->count();
        
        if ($orphaned > 0) {
            Appointment::whereNotIn('user_id', 
                User::select('id')->get()->pluck('id')
            )->delete();
            
            $this->command->line("  ✓ Removed $orphaned orphaned appointments");
        } else {
            $this->command->line("  ✓ No orphaned appointments found");
        }
    }

    private function showDatabaseStats(): void
    {
        $userCount = User::where('role', 'client')->count();
        $appointmentCount = Appointment::count();
        $adminCount = User::where('role', 'admin')->count();
        
        $this->command->info('📊 Database Statistics:');
        $this->command->line("   Admin users: $adminCount");
        $this->command->line("   Client users: $userCount");
        $this->command->line("   Total appointments: $appointmentCount");
        $this->command->line("   Average appointments per client: " . 
            number_format($appointmentCount / max($userCount, 1), 1));
    }
}
