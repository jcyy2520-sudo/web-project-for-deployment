<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add performance indexes to key tables for faster dashboard loading.
     */
    public function up()
    {
        // Helper to safely add index - catches duplicate index errors
        $addIndex = function (string $table, $columns, string $name) {
            try {
                Schema::table($table, function (Blueprint $table) use ($columns, $name) {
                    $table->index($columns, $name);
                });
            } catch (\Exception $e) {
                // Index already exists, skip silently
            }
        };

        // Appointments table indexes
        $addIndex('appointments', 'status', 'idx_appointments_status');
        $addIndex('appointments', 'appointment_date', 'idx_appointments_date');
        $addIndex('appointments', ['status', 'appointment_date'], 'idx_appointments_status_date');
        $addIndex('appointments', ['user_id', 'status'], 'idx_appointments_user_status');

        // Messages table indexes for faster conversation loading
        $addIndex('messages', ['sender_id', 'receiver_id'], 'idx_messages_sender_receiver');
        $addIndex('messages', ['receiver_id', 'read'], 'idx_messages_receiver_read');

        // Users table index for role-based queries
        $addIndex('users', ['role', 'is_active'], 'idx_users_role_active');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_status');
            $table->dropIndex('idx_appointments_date');
            $table->dropIndex('idx_appointments_status_date');
            $table->dropIndex('idx_appointments_user_status');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_sender_receiver');
            $table->dropIndex('idx_messages_receiver_read');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role_active');
        });
    }
};
