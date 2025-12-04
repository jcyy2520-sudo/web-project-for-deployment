<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Appointment;

class CleanupTestDataSeeder extends Seeder
{
    /**
     * Remove all test data created by TestUserSeeder and TestAppointmentsSeeder
     */
    public function run(): void
    {
        // Character names from TestUserSeeder
        $testCharacters = [
            // Naruto characters
            ['Naruto', 'Uzumaki'],
            ['Sasuke', 'Uchiha'],
            ['Sakura', 'Haruno'],
            ['Kakashi', 'Hatake'],
            ['Jiraiya', 'Sennin'],
            ['Hinata', 'Hyuga'],
            ['Itachi', 'Uchiha'],
            ['Madara', 'Uchiha'],
            
            // Pokemon characters
            ['Ash', 'Ketchum'],
            ['Misty', 'Waterflower'],
            ['Brock', 'Harrison'],
            ['Gary', 'Oak'],
            ['Pikachu', 'Pokemon'],
            ['Charizard', 'Pokemon'],
            ['Mewtwo', 'Pokemon'],
            ['Articuno', 'Pokemon'],
            
            // BINI members
            ['Aiah', 'Canapi'],
            ['Colet', 'Ledesma'],
            ['Maloi', 'Chee'],
            ['Gwen', 'Minang'],
            ['Stacey', 'Redoble'],
            ['Sheena', 'Catindig'],
            ['Mikha', 'Lim'],
            ['Jhoanna', 'Robles'],
        ];

        $deletedCount = 0;
        
        foreach ($testCharacters as [$firstName, $lastName]) {
            $email = strtolower(str_replace(' ', '.', $firstName . '.' . $lastName)) . '@gmail.com';
            
            // Find and delete user (cascade deletes appointments)
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->delete();
                $deletedCount++;
            }
        }

        $this->command->info("✓ Cleanup complete!");
        $this->command->info("Deleted $deletedCount test users and their appointments");
    }
}
