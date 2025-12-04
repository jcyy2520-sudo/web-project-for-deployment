<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin account - use firstOrCreate to prevent duplicates
        User::firstOrCreate(
            ['email' => 'johncyy2025@gmail.com'],
            [
                'username' => 'admin',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'phone' => '09123456789',
                'address' => 'System Address',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Admin user created/verified successfully!');
        $this->command->info('Email: johncyy2025@gmail.com');
        $this->command->info('Password: admin123');
        
        // Run other seeders (disabled to prevent test data in production)
        // $this->call([
        //     TestUserSeeder::class,
        //     TestAppointmentsSeeder::class,
        // ]);
    }
}