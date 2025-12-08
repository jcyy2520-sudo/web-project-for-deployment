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
            ['name' => 'Cash', 'slug' => 'cash', 'description' => 'Cash payment', 'display_name' => 'Cash'],
            ['name' => 'Check', 'slug' => 'check', 'description' => 'Payment by check', 'display_name' => 'Check'],
            ['name' => 'Bank Transfer', 'slug' => 'bank_transfer', 'description' => 'Bank transfer or online payment', 'display_name' => 'Bank Transfer'],
            ['name' => 'Card', 'slug' => 'card', 'description' => 'Credit/Debit card payment', 'display_name' => 'Card'],
            ['name' => 'Goods/Barter', 'slug' => 'goods_barter', 'description' => 'Payment in goods or services', 'display_name' => 'Goods/Barter'],
            ['name' => 'Discount Applied', 'slug' => 'discount', 'description' => 'Discounted price', 'display_name' => 'Discount'],
            ['name' => 'Mixed Methods', 'slug' => 'mixed', 'description' => 'Multiple payment methods', 'display_name' => 'Mixed'],
            ['name' => 'Write-off', 'slug' => 'write_off', 'description' => 'Debt forgiveness or write-off', 'display_name' => 'Write-off'],
        ];

        // Skip payment method seeding if they already exist
        if (PaymentMethod::count() === 0) {
            foreach ($methods as $method) {
                PaymentMethod::create($method);
            }
        }
    }
}
