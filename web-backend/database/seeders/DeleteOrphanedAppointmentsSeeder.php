<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Appointment;
use App\Models\User;

class DeleteOrphanedAppointmentsSeeder extends Seeder
{
    /**
     * Delete all appointments that belong to soft-deleted users
     */
    public function run(): void
    {
        // Get all soft-deleted users
        $deletedUsers = User::onlyTrashed()->pluck('id')->toArray();
        
        if (empty($deletedUsers)) {
            $this->command->info('No soft-deleted users found.');
            return;
        }

        // Delete appointments belonging to deleted users
        $deletedCount = Appointment::whereIn('user_id', $deletedUsers)->delete();

        $this->command->info("✓ Deleted $deletedCount orphaned appointments");
        $this->command->info("Database is now clean!");
    }
}
