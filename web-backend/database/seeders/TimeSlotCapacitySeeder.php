<?php

namespace Database\Seeders;

use App\Models\TimeSlotCapacity;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TimeSlotCapacitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing capacities
        TimeSlotCapacity::truncate();

        // Create default capacity of 3 appointments per hour for all time slots
        // Monday to Friday, 8 AM to 5 PM (excluding lunch 12-1 PM)
        
        $workingDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $timeSlots = [
            ['start' => '08:00', 'end' => '09:00'],
            ['start' => '09:00', 'end' => '10:00'],
            ['start' => '10:00', 'end' => '11:00'],
            ['start' => '11:00', 'end' => '12:00'],
            // Lunch break: 12:00-13:00 is skipped
            ['start' => '13:00', 'end' => '14:00'],
            ['start' => '14:00', 'end' => '15:00'],
            ['start' => '15:00', 'end' => '16:00'],
            ['start' => '16:00', 'end' => '17:00'],
        ];

        // Create capacity configurations for each working day and time slot
        foreach ($workingDays as $day) {
            foreach ($timeSlots as $slot) {
                TimeSlotCapacity::create([
                    'day_of_week' => $day,
                    'start_time' => $slot['start'],
                    'end_time' => $slot['end'],
                    'max_appointments_per_slot' => 3,
                    'is_active' => true,
                    'description' => "Default capacity for {$day} {$slot['start']}-{$slot['end']}"
                ]);
            }
        }

        $this->command->info('TimeSlotCapacity seeded successfully!');
        $this->command->info('Created ' . (count($workingDays) * count($timeSlots)) . ' capacity configurations');
        $this->command->info('Default capacity: 3 appointments per hour');
        $this->command->info('Working hours: Monday-Friday, 8:00 AM - 5:00 PM (lunch 12:00-1:00 PM excluded)');
    }
}
