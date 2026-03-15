<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the ML outcome logs table for tracking predictions vs actual outcomes.
     */
    public function up(): void
    {
        Schema::create('ml_outcome_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->string('prediction_type')->default('risk'); // risk, staff, slot
            $table->string('predicted_outcome')->nullable();
            $table->float('predicted_probability')->nullable();
            $table->string('actual_outcome'); // completed, cancelled, no_show
            $table->string('staff_feedback')->nullable(); // accepted, rejected, overridden
            $table->string('staff_feedback_reason')->nullable();
            $table->unsignedBigInteger('logged_by')->nullable();
            $table->timestamps();

            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
            $table->foreign('logged_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['appointment_id', 'created_at']);
            $table->index('prediction_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ml_outcome_logs');
    }
};
