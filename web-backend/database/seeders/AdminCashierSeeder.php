<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminCashierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin account
        $admin = User::firstOrCreate(
            ['email' => 'admindeguzman@gmail.com'],
            [
                'username' => 'admindeguzman',
                'password' => Hash::make('deguzmanlegal2026'),
                'role' => 'admin',
                'first_name' => 'Admin',
                'last_name' => 'Deguzman',
                'phone' => '09000000001',
                'address' => 'System Address',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✓ Admin account created/verified successfully!');
        $this->command->info('  Email: admindeguzman@gmail.com');
        $this->command->info('  Role: admin');
        $this->command->line('');

        // Cashier account
        $cashier = User::firstOrCreate(
            ['email' => 'cashierdeguzman@gmail.com'],
            [
                'username' => 'cashierdeguzman',
                'password' => Hash::make('deguzmancashier12345'),
                'role' => 'staff',
                'first_name' => 'Cashier',
                'last_name' => 'Deguzman',
                'phone' => '09000000002',
                'address' => 'System Address',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✓ Cashier account created/verified successfully!');
        $this->command->info('  Email: cashierdeguzman@gmail.com');
        $this->command->info('  Role: staff (cashier access)');
    }
}
