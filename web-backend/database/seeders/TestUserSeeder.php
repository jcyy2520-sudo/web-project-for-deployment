<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Character data - Naruto, Pokemon, and BINI members
        $characters = [
            // Naruto characters
            ['first_name' => 'Naruto', 'last_name' => 'Uzumaki', 'username' => 'naruto_uzumaki'],
            ['first_name' => 'Sasuke', 'last_name' => 'Uchiha', 'username' => 'sasuke_uchiha'],
            ['first_name' => 'Sakura', 'last_name' => 'Haruno', 'username' => 'sakura_haruno'],
            ['first_name' => 'Kakashi', 'last_name' => 'Hatake', 'username' => 'kakashi_hatake'],
            ['first_name' => 'Jiraiya', 'last_name' => 'Sennin', 'username' => 'jiraiya_sennin'],
            ['first_name' => 'Hinata', 'last_name' => 'Hyuga', 'username' => 'hinata_hyuga'],
            ['first_name' => 'Itachi', 'last_name' => 'Uchiha', 'username' => 'itachi_uchiha'],
            ['first_name' => 'Madara', 'last_name' => 'Uchiha', 'username' => 'madara_uchiha'],
            
            // Pokemon characters
            ['first_name' => 'Ash', 'last_name' => 'Ketchum', 'username' => 'ash_ketchum'],
            ['first_name' => 'Misty', 'last_name' => 'Waterflower', 'username' => 'misty_waterflower'],
            ['first_name' => 'Brock', 'last_name' => 'Harrison', 'username' => 'brock_harrison'],
            ['first_name' => 'Gary', 'last_name' => 'Oak', 'username' => 'gary_oak'],
            ['first_name' => 'Pikachu', 'last_name' => 'Pokemon', 'username' => 'pikachu_pokemon'],
            ['first_name' => 'Charizard', 'last_name' => 'Pokemon', 'username' => 'charizard_pokemon'],
            ['first_name' => 'Mewtwo', 'last_name' => 'Pokemon', 'username' => 'mewtwo_pokemon'],
            ['first_name' => 'Articuno', 'last_name' => 'Pokemon', 'username' => 'articuno_pokemon'],
            
            // BINI members (P-pop group from Philippines)
            ['first_name' => 'Aiah', 'last_name' => 'Canapi', 'username' => 'aiah_canapi'],
            ['first_name' => 'Colet', 'last_name' => 'Ledesma', 'username' => 'colet_ledesma'],
            ['first_name' => 'Maloi', 'last_name' => 'Chee', 'username' => 'maloi_chee'],
            ['first_name' => 'Gwen', 'last_name' => 'Minang', 'username' => 'gwen_minang'],
            ['first_name' => 'Stacey', 'last_name' => 'Redoble', 'username' => 'stacey_redoble'],
            ['first_name' => 'Sheena', 'last_name' => 'Catindig', 'username' => 'sheena_catindig'],
            ['first_name' => 'Mikha', 'last_name' => 'Lim', 'username' => 'mikha_lim'],
            ['first_name' => 'Jhoanna', 'last_name' => 'Robles', 'username' => 'jhoanna_robles'],
        ];

        $phoneCounter = 9000000001; // Starts from 09000000001 (11 digits total with leading 0)
        $userCount = 0;
        $createdCount = 0;
        
        foreach ($characters as $character) {
            $email = strtolower(str_replace(' ', '.', $character['first_name'] . '.' . $character['last_name'])) . '@gmail.com';
            $phone = '0' . $phoneCounter++;
            
            // Check if user exists (including soft deleted)
            $existingUser = User::withTrashed()->where('email', $email)->first();
            
            if ($existingUser) {
                // User exists - restore if soft deleted
                if ($existingUser->trashed()) {
                    $existingUser->restore();
                    $existingUser->update([
                        'is_active' => true,
                        'password' => bcrypt('password123')
                    ]);
                    $createdCount++;
                }
            } else {
                // User doesn't exist - create new
                User::create([
                    'email' => $email,
                    'username' => $character['username'],
                    'password' => bcrypt('password123'),
                    'first_name' => $character['first_name'],
                    'last_name' => $character['last_name'],
                    'phone' => $phone,
                    'role' => 'client',
                    'is_active' => true,
                    'address' => 'Philippines',
                ]);
                $createdCount++;
            }
            $userCount++;
        }

        $this->command->info("✓ $userCount test users processed!");
        $this->command->info("✓ $createdCount users created/restored");
        $this->command->line('All users have password: password123');
    }
}
