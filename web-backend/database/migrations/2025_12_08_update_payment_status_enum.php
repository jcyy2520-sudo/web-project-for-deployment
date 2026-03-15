<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change the enum to include refund statuses
        Schema::table('appointments', function (Blueprint $table) {
            // Drop the old enum and recreate with new values
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement("ALTER TABLE appointments MODIFY payment_status ENUM('unpaid', 'paid', 'partial', 'refunded', 'partially_refunded') DEFAULT 'unpaid'");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        Schema::table('appointments', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement("ALTER TABLE appointments MODIFY payment_status ENUM('unpaid', 'paid', 'partial') DEFAULT 'unpaid'");
            }
        });
    }
};
