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
        // Add indexes for booking limit and appointment lookups
        Schema::table('appointments', function (Blueprint $table) {
            // Index for getBookingsInLast24Hours which filters by user_id, created_at, and status
            $table->index(['user_id', 'created_at', 'status'], 'idx_apt_limit_check');
            
            // Index for general user appointment lookups (Dashboard/Chatbot)
            $table->index(['user_id', 'appointment_date', 'status'], 'idx_apt_user_date_status');
        });

        // Add indexes for other frequently queried tables that impact performance
        Schema::table('action_logs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'idx_action_logs_user_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_apt_limit_check');
            $table->dropIndex('idx_apt_user_date_status');
        });

        Schema::table('action_logs', function (Blueprint $table) {
            $table->dropIndex('idx_action_logs_user_date');
        });
    }
};
