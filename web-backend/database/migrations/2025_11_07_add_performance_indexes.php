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
        Schema::table('messages', function (Blueprint $table) {
            // Index for reply_to_message_id to speed up reply lookups
            if (!Schema::hasColumn('messages', 'reply_to_message_id')) {
                $table->index('reply_to_message_id', 'idx_messages_reply_to');
            }
            
            // Indexes for sender/receiver lookups
            $table->index('sender_id', 'idx_messages_sender');
            $table->index('receiver_id', 'idx_messages_receiver');
            
            // Composite index for common query: find messages from user to receiver
            $table->index(['sender_id', 'receiver_id'], 'idx_messages_sender_receiver');
            
            // Index for reply counting queries
            if (Schema::hasColumn('messages', 'reply_to_message_id')) {
                $table->index(['reply_to_message_id', 'sender_id'], 'idx_messages_reply_sender');
            }
            
            // Index for read status queries
            $table->index(['receiver_id', 'read'], 'idx_messages_receiver_read');
        });

        Schema::table('appointments', function (Blueprint $table) {
            // Indexes for common filtering
            $table->index('status', 'idx_appointments_status');
            $table->index('user_id', 'idx_appointments_user');
            $table->index('staff_id', 'idx_appointments_staff');
            
            // Composite indexes for common queries
            $table->index(['appointment_date', 'appointment_time'], 'idx_appointments_datetime');
            $table->index(['status', 'appointment_date'], 'idx_appointments_status_date');
        });

        Schema::table('users', function (Blueprint $table) {
            // Index for common lookups
            $table->index('role', 'idx_users_role');
            
            // Only add this index if deleted_at column exists
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->index(['email', 'deleted_at'], 'idx_users_email_deleted');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_reply_to');
            $table->dropIndex('idx_messages_sender');
            $table->dropIndex('idx_messages_receiver');
            $table->dropIndex('idx_messages_sender_receiver');
            $table->dropIndex('idx_messages_reply_sender');
            $table->dropIndex('idx_messages_receiver_read');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_status');
            $table->dropIndex('idx_appointments_user');
            $table->dropIndex('idx_appointments_staff');
            $table->dropIndex('idx_appointments_datetime');
            $table->dropIndex('idx_appointments_status_date');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role');
            $table->dropIndex('idx_users_email_deleted');
        });
    }
};
