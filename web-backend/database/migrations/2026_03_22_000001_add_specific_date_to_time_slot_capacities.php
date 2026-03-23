<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_slot_capacities', function (Blueprint $table) {
            $table->date('specific_date')->nullable()->after('day_of_week');
            
            // Drop old unique constraint (day_of_week, start_time, end_time)
            // and add new one that includes specific_date
            $table->dropUnique(['day_of_week', 'start_time', 'end_time']);
            $table->unique(['day_of_week', 'specific_date', 'start_time', 'end_time'], 'tsc_unique_slot');
        });
    }

    public function down(): void
    {
        Schema::table('time_slot_capacities', function (Blueprint $table) {
            $table->dropUnique('tsc_unique_slot');
            $table->unique(['day_of_week', 'start_time', 'end_time']);
            $table->dropColumn('specific_date');
        });
    }
};
