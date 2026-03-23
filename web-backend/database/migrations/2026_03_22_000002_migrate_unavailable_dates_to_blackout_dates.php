<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate all unavailable_dates rows into blackout_dates
        if (Schema::hasTable('unavailable_dates') && Schema::hasTable('blackout_dates')) {
            $legacyDates = DB::table('unavailable_dates')->get();

            foreach ($legacyDates as $legacy) {
                // Check for duplicate: same date with no time range (all-day)
                $exists = DB::table('blackout_dates')
                    ->where('date', $legacy->date)
                    ->where('is_recurring', false)
                    ->when($legacy->all_day, function ($q) {
                        $q->whereNull('start_time')->whereNull('end_time');
                    }, function ($q) use ($legacy) {
                        $q->where('start_time', $legacy->start_time)
                          ->where('end_time', $legacy->end_time);
                    })
                    ->exists();

                if (!$exists) {
                    DB::table('blackout_dates')->insert([
                        'date' => $legacy->date,
                        'reason' => $legacy->reason,
                        'start_time' => $legacy->all_day ? null : $legacy->start_time,
                        'end_time' => $legacy->all_day ? null : $legacy->end_time,
                        'is_recurring' => false,
                        'recurring_days' => null,
                        'created_at' => $legacy->created_at,
                        'updated_at' => $legacy->updated_at,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // No reverse needed — unavailable_dates table is preserved as-is
    }
};
