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
        $paymentStatuses = ['paid', 'unpaid', 'partial'];
        $times = ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'];
        $identificationTypes = ['Passport', 'Driver License', 'National ID', 'TIN', 'SSN'];
        $appointmentCreated = 0;

        foreach ($users as $user) {
            // Create exactly 2 appointments per user
            for ($i = 0; $i < 2; $i++) {
                // Spread appointments across different dates
                $randomDays = rand(-45, 45);
                $appointmentDate = Carbon::now()->addDays($randomDays);
                $date = $appointmentDate->format('Y-m-d');
                
                $service = $services->random();
                $timeStr = $times[array_rand($times)];
                $status = $statuses[array_rand($statuses)];
                $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];
                $idType = $identificationTypes[array_rand($identificationTypes)];
                
                // Calculate end time based on service duration
                $startTime = Carbon::createFromFormat('H:i', $timeStr);
                $endTime = $startTime->copy()->addMinutes($service->duration ?? 30);

                // Set payment amount based on status
                $paymentAmount = 0;
                if ($status === 'approved' || $status === 'completed') {
                    $paymentAmount = $service->price;
                }

                $appointment = Appointment::create([
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'appointment_date' => $date,
                    'appointment_time' => $timeStr,
                    'start_time' => $startTime->format('H:i:s'),
                    'end_time' => $endTime->format('H:i:s'),
                    'discount_amount' => 0,
                    'discount_type' => null,
                    'type' => 'in-person',
                    'identification_type' => $idType,
                    'purpose' => 'Regular appointment',
                    'notes' => 'Appointment for ' . $user->first_name . ' ' . $user->last_name,
                    'payment_date' => ($paymentStatus === 'paid' || $paymentStatus === 'partial') ? now() : null,
                ]);
                // Set protected fields explicitly (not mass-assignable)
                $appointment->status = $status;
                $appointment->payment_status = $paymentStatus;
                $appointment->payment_amount = $paymentStatus === 'paid' ? $paymentAmount : ($paymentStatus === 'partial' ? $paymentAmount * 0.5 : 0);
                $appointment->save();
                
                $appointmentCreated++;
            }
        }

        $this->command->info("✓ Appointments created successfully!");
        $this->command->info("Total: $appointmentCreated appointments for " . count($users) . " users");
        $this->command->info("Average: 2 appointments per user");
        $this->command->info("All appointments now have:");
        $this->command->info("  - Payment status and amount");
        $this->command->info("  - Start/end times");
        $this->command->info("  - Identification types");
    }
}
