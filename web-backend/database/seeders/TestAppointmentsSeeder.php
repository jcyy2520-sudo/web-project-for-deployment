<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Service;
use Carbon\Carbon;

class TestAppointmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all client users (skip admin)
        $users = User::where('role', 'client')->get();

        // Create default services if they don't exist
        $services = Service::where('is_active', true)->get();
        if ($services->isEmpty()) {
            Service::create([
                'name' => 'Consultation',
                'description' => 'Initial consultation',
                'duration' => 30,
                'price' => 50.00,
                'is_active' => true,
            ]);
            Service::create([
                'name' => 'Follow-up',
                'description' => 'Follow-up appointment',
                'duration' => 45,
                'price' => 75.00,
                'is_active' => true,
            ]);
            Service::create([
                'name' => 'Assessment',
                'description' => 'Professional assessment',
                'duration' => 60,
                'price' => 100.00,
                'is_active' => true,
            ]);
            Service::create([
                'name' => 'Treatment',
                'description' => 'Treatment session',
                'duration' => 90,
                'price' => 150.00,
                'is_active' => true,
            ]);
            
            $services = Service::where('is_active', true)->get();
        }

        $statuses = ['completed', 'approved', 'pending', 'declined'];
        $times = ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'];
        $appointmentCreated = 0;

        foreach ($users as $user) {
            // Create exactly 2 appointments per user
            for ($i = 0; $i < 2; $i++) {
                // Spread appointments across different dates
                $randomDays = rand(-45, 45);
                $date = Carbon::now()->addDays($randomDays)->format('Y-m-d');
                
                $service = $services->random();
                $time = $times[array_rand($times)];
                $status = $statuses[array_rand($statuses)];

                Appointment::create([
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'appointment_date' => $date,
                    'appointment_time' => $time,
                    'status' => $status,
                    'type' => 'in-person',
                    'purpose' => 'Regular appointment',
                    'notes' => 'Appointment for ' . $user->first_name . ' ' . $user->last_name,
                ]);
                
                $appointmentCreated++;
            }
        }

        $this->command->info("✓ Appointments created successfully!");
        $this->command->info("Total: $appointmentCreated appointments for " . count($users) . " users");
        $this->command->info("Average: 2 appointments per user");
    }
}
