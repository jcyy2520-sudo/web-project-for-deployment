<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class UserWithAppointmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Character data - Naruto, Pokemon, and BINI members
        $characters = [
            // Naruto characters
            ['first_name' => 'Naruto', 'last_name' => 'Uzumaki'],
            ['first_name' => 'Sasuke', 'last_name' => 'Uchiha'],
            ['first_name' => 'Sakura', 'last_name' => 'Haruno'],
            ['first_name' => 'Kakashi', 'last_name' => 'Hatake'],
            ['first_name' => 'Jiraiya', 'last_name' => 'Sennin'],
            ['first_name' => 'Hinata', 'last_name' => 'Hyuga'],
            ['first_name' => 'Itachi', 'last_name' => 'Uchiha'],
            ['first_name' => 'Madara', 'last_name' => 'Uchiha'],
            
            // Pokemon characters
            ['first_name' => 'Ash', 'last_name' => 'Ketchum'],
            ['first_name' => 'Misty', 'last_name' => 'Waterflower'],
            ['first_name' => 'Brock', 'last_name' => 'Harrison'],
            ['first_name' => 'Gary', 'last_name' => 'Oak'],
            ['first_name' => 'Pikachu', 'last_name' => 'Pokemon'],
            ['first_name' => 'Charizard', 'last_name' => 'Pokemon'],
            ['first_name' => 'Mewtwo', 'last_name' => 'Pokemon'],
            ['first_name' => 'Articuno', 'last_name' => 'Pokemon'],
            
            // BINI members (P-pop group from Philippines)
            ['first_name' => 'Aiah', 'last_name' => 'Canapi'],
            ['first_name' => 'Colet', 'last_name' => 'Ledesma'],
            ['first_name' => 'Maloi', 'last_name' => 'Chee'],
            ['first_name' => 'Gwen', 'last_name' => 'Minang'],
            ['first_name' => 'Stacey', 'last_name' => 'Redoble'],
            ['first_name' => 'Sheena', 'last_name' => 'Catindig'],
            ['first_name' => 'Mikha', 'last_name' => 'Lim'],
            ['first_name' => 'Jhoanna', 'last_name' => 'Robles'],
            
            // Additional characters to reach 30
            ['first_name' => 'Shikamaru', 'last_name' => 'Nara'],
            ['first_name' => 'Ino', 'last_name' => 'Yamanaka'],
            ['first_name' => 'Choji', 'last_name' => 'Akimichi'],
            ['first_name' => 'Rock', 'last_name' => 'Lee'],
            ['first_name' => 'Neji', 'last_name' => 'Hyuga'],
            ['first_name' => 'Tenten', 'last_name' => 'Uzushio'],
        ];

        // Get all services (must be seeded first)
        $services = Service::all();

        if ($services->isEmpty()) {
            $this->command->error('No services found! Please seed ServiceSeeder first.');
            return;
        }

        // Delete existing client users and their appointments
        $clientUsers = User::where('role', 'client')->get();
        foreach ($clientUsers as $user) {
            $user->appointments()->delete();
            $user->delete();
        }

        $phoneCounter = 9000000001;
        $appointmentDate = Carbon::now()->addDays(1);
        $times = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00'];
        $timeIndex = 0;

        foreach ($characters as $index => $character) {
            // Create username from first and last name
            $username = strtolower($character['first_name'] . '.' . $character['last_name']);
            $email = strtolower($character['first_name'] . '.' . $character['last_name'] . '@example.com');

            // Create user
            $user = User::create([
                'first_name' => $character['first_name'],
                'last_name' => $character['last_name'],
                'username' => $username,
                'email' => $email,
                'phone' => '0' . $phoneCounter++,
                'password' => Hash::make('password123'),
                'role' => 'client',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            // Create 2 appointments for each user
            for ($i = 0; $i < 2; $i++) {
                // Randomize service selection
                $service = $services->random();

                // Cycle through times
                $appointmentTime = $times[$timeIndex % count($times)];
                $timeIndex++;

                // Vary appointment dates
                $currentDate = $appointmentDate->copy()->addDays($i);

                Appointment::create([
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'appointment_date' => $currentDate,
                    'appointment_time' => $appointmentTime,
                    'status' => $this->randomStatus(),
                    'payment_status' => 'unpaid',
                    'purpose' => 'Legal service consultation - ' . $service->name,
                    'type' => 'in-person',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Users and appointments seeded successfully!');
        $this->command->info('Created 30 users with 2 appointments each (60 total appointments)');
    }

    private function randomStatus()
    {
        $statuses = ['pending', 'approved', 'completed', 'cancelled'];
        return $statuses[array_rand($statuses)];
    }
}
