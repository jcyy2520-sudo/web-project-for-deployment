<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds reminder_level to track multi-stage reminders:
     * 0 = no reminder sent
     * 1 = 2-hour reminder sent
     * 2 = 1-hour reminder sent  
     * 3 = 30-minute reminder sent
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedTinyInteger('reminder_level')->default(0)->after('reminder_sent')
                ->comment('0=none, 1=2hr sent, 2=1hr sent, 3=30min sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('reminder_level');
        });
    }
};
