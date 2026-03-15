<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration adds crucial performance indexes to frequently queried columns
     * It should dramatically reduce query time for:
     * - Admin dashboard stats loading
     * - User list filtering
     * - Appointment filtering and pagination
     */
    public function up(): void
    {
        // Appointments table indexes - most critical for dashboard
        Schema::table('appointments', function (Blueprint $table) {
            // Check if index already exists before creating
            if (!Schema::hasIndex('appointments', 'idx_appointments_status')) {
                $table->index('status', 'idx_appointments_status');
            }
            
            // Index for user appointments
            if (!Schema::hasIndex('appointments', 'idx_appointments_user_id')) {
                $table->index('user_id', 'idx_appointments_user_id');
            }
        });

        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasIndex('users', 'idx_users_role')) {
                $table->index('role', 'idx_users_role');
            }
            
            if (!Schema::hasIndex('users', 'idx_users_is_active')) {
                $table->index('is_active', 'idx_users_is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Safely drop index if it exists
            try {
                $table->dropIndex('idx_appointments_status');
            } catch (\Exception $e) {
                // Index doesn't exist, continue
            }
            
            try {
                $table->dropIndex('idx_appointments_user_id');
            } catch (\Exception $e) {
                // Index doesn't exist, continue
            }
        });

        Schema::table('users', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_users_role');
            } catch (\Exception $e) {
                // Index doesn't exist, continue
            }
            
            try {
                $table->dropIndex('idx_users_is_active');
            } catch (\Exception $e) {
                // Index doesn't exist, continue
            }
        });
    }
};
