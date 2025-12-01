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
        // Add indexes to appointments table for common queries
        Schema::table('appointments', function (Blueprint $table) {
            // Index for status filtering in stats queries
            if (!Schema::hasColumn('appointments', 'status')) {
                $table->index('status')->after('status');
            } else {
                $table->index('status');
            }
            
            // Index for date range queries in dashboard
            $table->index('appointment_date');
            
            // Composite index for status + date queries (stats by period)
            $table->index(['status', 'appointment_date']);
            
            // Index for service_id joins in revenue calculations
            $table->index('service_id');
            
            // Index for user_id queries
            $table->index('user_id');
        });

        // Add indexes to services table
        Schema::table('services', function (Blueprint $table) {
            // Index for lookups by service_id
            if (!Schema::hasColumn('services', 'id')) {
                // Table already has primary key
            } else {
                $table->index('id');
            }
        });

        // Add indexes to users table for role filtering
        Schema::table('users', function (Blueprint $table) {
            // Index for role-based queries (counting clients, staff, admins)
            $table->index('role');
            
            // Index for date range queries (user growth)
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['appointment_date']);
            $table->dropIndex(['status', 'appointment_date']);
            $table->dropIndex(['service_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['created_at']);
        });
    }
};
