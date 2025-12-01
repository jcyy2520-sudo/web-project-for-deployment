<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_slot_capacities', function (Blueprint $table) {
            $table->id();
            $table->string('day_of_week')->nullable(); // 'monday', 'tuesday', etc. - null for all days
            $table->time('start_time'); // e.g., 09:00
            $table->time('end_time');   // e.g., 17:00
            $table->integer('max_appointments_per_slot')->default(3); // Max clients per hour
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            // Composite unique index to prevent duplicate time slot configurations
            $table->unique(['day_of_week', 'start_time', 'end_time']);
        });

        Schema::create('blackout_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('reason')->nullable(); // e.g., 'Holiday', 'Closed for Maintenance'
            $table->time('start_time')->nullable(); // If null, entire day is blocked
            $table->time('end_time')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->json('recurring_days')->nullable(); // ['monday', 'friday', etc.]
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_slot_capacities');
        Schema::dropIfExists('blackout_dates');
    }
};
