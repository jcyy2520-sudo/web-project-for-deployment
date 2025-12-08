<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

class RemoveDuplicatesSeeder extends Seeder
{
    /**
     * Remove duplicate users and appointments while preserving manual data
     * Keeps first occurrence, deletes duplicates
     */
    public function run(): void
    {
        $this->command->info('🧹 Starting duplicate cleanup...');
        
        // Remove duplicate users
        $this->removeDuplicateUsers();
        
        // Remove duplicate appointments
        $this->removeDuplicateAppointments();
        
        $this->command->info('✅ Cleanup completed successfully!');
    }

    private function removeDuplicateUsers(): void
    {
        $this->command->line('Checking for duplicate users...');
        
        // Find duplicate emails (excluding admin)
        $duplicates = DB::table('users')
            ->where('role', 'client')
            ->select('email', DB::raw('COUNT(*) as count'))
            ->groupBy('email')
            ->having('count', '>', 1)
            ->get();
        
        if ($duplicates->isEmpty()) {
            $this->command->info('  ✓ No duplicate users found');
            return;
        }

        $deletedCount = 0;
        foreach ($duplicates as $duplicate) {
            // Get all records with this email, ordered by ID (keep first)
            $users = User::where('email', $duplicate->email)
                ->orderBy('id', 'asc')
                ->get();
            
            // Keep the first one, delete the rest
            foreach ($users->skip(1) as $user) {
                // Delete related appointments first
                Appointment::where('user_id', $user->id)->delete();
                $user->delete();
                $deletedCount++;
            }
            
            $this->command->line("  ✓ Removed " . ($duplicate->count - 1) . " duplicate(s) for email: {$duplicate->email}");
        }
        
        $this->command->info("Total duplicate users removed: $deletedCount");
    }

    private function removeDuplicateAppointments(): void
    {
        $this->command->line('Checking for duplicate appointments...');
        
        // Find exact duplicates: same user, same date, same time, same service
        $appointments = Appointment::all()->groupBy(function ($item) {
            return $item->user_id . '|' . $item->appointment_date . '|' . $item->appointment_time . '|' . $item->service_id;
        });
        
        $deletedCount = 0;
        foreach ($appointments as $group) {
            if ($group->count() > 1) {
                // Keep first, delete duplicates
                $sorted = $group->sortBy('id');
                foreach ($sorted->skip(1) as $appointment) {
                    $appointment->delete();
                    $deletedCount++;
                }
                
                $this->command->line("  ✓ Removed " . ($group->count() - 1) . 
                    " duplicate(s) for user {$group[0]->user_id} on {$group[0]->appointment_date} at {$group[0]->appointment_time}");
            }
        }
        
        $this->command->info("Total duplicate appointments removed: $deletedCount");
    }
}
