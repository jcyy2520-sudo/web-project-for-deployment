<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discount_rates', function (Blueprint $table) {
            $table->id();
            $table->string('discount_type')->unique(); // 'pwd', 'senior_citizen', 'student'
            $table->decimal('discount_percentage', 5, 2); // e.g., 15.00 for 15%
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default discount rates
        DB::table('discount_rates')->insert([
            ['discount_type' => 'pwd', 'discount_percentage' => 15.00, 'description' => 'Persons with Disability Discount', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['discount_type' => 'senior_citizen', 'discount_percentage' => 10.00, 'description' => 'Senior Citizen Discount', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['discount_type' => 'student', 'discount_percentage' => 10.00, 'description' => 'Student Discount', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_rates');
    }
};
