<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create payment methods only if they don't exist
        $methods = [
            ['name' => 'Cash', 'slug' => 'cash', 'description' => 'Cash payment'],
            ['name' => 'Check', 'slug' => 'check', 'description' => 'Payment by check'],
            ['name' => 'Bank Transfer', 'slug' => 'bank_transfer', 'description' => 'Bank transfer or online payment'],
            ['name' => 'Card', 'slug' => 'card', 'description' => 'Credit/Debit card payment'],
            ['name' => 'Goods/Barter', 'slug' => 'goods_barter', 'description' => 'Payment in goods or services'],
            ['name' => 'Discount Applied', 'slug' => 'discount', 'description' => 'Discounted price'],
            ['name' => 'Mixed Methods', 'slug' => 'mixed', 'description' => 'Multiple payment methods'],
            ['name' => 'Write-off', 'slug' => 'write_off', 'description' => 'Debt forgiveness or write-off'],
        ];

        // Skip payment method seeding if they already exist
        if (PaymentMethod::count() === 0) {
            foreach ($methods as $method) {
                PaymentMethod::create($method);
            }
        }
    }
}
