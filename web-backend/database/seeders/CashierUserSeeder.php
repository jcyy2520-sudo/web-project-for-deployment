<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CashierUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Cashier user for testing cashier dashboard
        User::firstOrCreate(
            ['email' => 'christiannjc25@gmail.com'],
            [
                'username' => 'christiannjc25',
                'password' => Hash::make('admin123'),
                'role' => 'staff', // Staff role for cashier access
                'first_name' => 'Christian',
                'last_name' => 'Admin',
                'phone' => '09123456789',
                'address' => 'System Address',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Cashier user created/verified successfully!');
        $this->command->info('Email: christiannjc25@gmail.com');
        $this->command->info('Password: admin123');
        $this->command->info('Role: staff (can access cashier dashboard)');
    }
}
