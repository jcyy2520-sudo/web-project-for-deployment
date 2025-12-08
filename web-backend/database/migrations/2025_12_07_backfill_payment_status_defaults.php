<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Set default payment_status for NULL values based on status
        DB::table('appointments')
            ->whereNull('payment_status')
            ->update(['payment_status' => 'unpaid']);
        
        // Set default payment_status based on appointment status if still null
        DB::table('appointments')
            ->where('status', 'completed')
            ->whereNull('payment_status')
            ->update(['payment_status' => 'paid']);
        
        // Ensure all approved appointments have payment info
        DB::table('appointments')
            ->where('status', 'approved')
            ->whereNull('payment_amount')
            ->update(['payment_amount' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration only backfills data, no need to reverse
    }
};
