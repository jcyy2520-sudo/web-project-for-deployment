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
        // Update any services with NULL prices or durations to default values
        \DB::table('services')
            ->whereNull('price')
            ->update(['price' => 0.00]);
        
        \DB::table('services')
            ->whereNull('duration')
            ->update(['duration' => 60]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No specific rollback needed as this is just data cleanup
        // If we need to track this separately, we would need to save the original values
    }
};
