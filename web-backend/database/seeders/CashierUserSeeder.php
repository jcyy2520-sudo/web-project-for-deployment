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
            ['email' => 'jcfajutagana3@gmail.com'],
            [
                'username' => 'jcfajutagana3',
                'password' => Hash::make('admin123'),
                'role' => 'staff', // Staff role for cashier access
                'first_name' => 'Cashier',
                'last_name' => 'User',
                'phone' => '09123456789',
                'address' => 'System Address',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Cashier user created/verified successfully!');
        $this->command->info('Email: jcfajutagana3@gmail.com');
        $this->command->info('Password: admin123');
        $this->command->info('Role: staff (can access cashier dashboard)');
    }
}
