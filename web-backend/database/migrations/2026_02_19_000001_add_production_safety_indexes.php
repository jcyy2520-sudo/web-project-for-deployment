<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production Safety Migration — Adds compound indexes for critical queries
 * and a unique constraint to prevent duplicate payments.
 * 
 * Safe: Only adds new indexes (non-destructive). Does not modify data or columns.
 * Reversible: down() method drops all added indexes cleanly.
 */
return new class extends Migration
{
    /**
     * Helper: check if an index exists on a table (Laravel 12 compatible).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn ($idx) => $idx['name'] === $indexName);
    }

    public function up(): void
    {
        // 1. Compound index for appointment capacity checks (prevents full table scans)
        // Used in: AppointmentController::store() — WHERE appointment_date = ? AND appointment_time = ? AND status IN (...)
        Schema::table('appointments', function (Blueprint $table) {
            if (!$this->indexExists('appointments', 'idx_apt_date_time_status')) {
                $table->index(['appointment_date', 'appointment_time', 'status'], 'idx_apt_date_time_status');
            }

            if (!$this->indexExists('appointments', 'idx_apt_user_status')) {
                $table->index(['user_id', 'status'], 'idx_apt_user_status');
            }
        });

        // 2. Unique constraint on payments.appointment_id (prevents duplicate payments)
        Schema::table('payments', function (Blueprint $table) {
            if (!$this->indexExists('payments', 'payments_appointment_id_unique')) {
                $table->unique('appointment_id', 'payments_appointment_id_unique');
            }
        });

        // 3. Compound indexes for messages (conversation lookups)
        Schema::table('messages', function (Blueprint $table) {
            if (!$this->indexExists('messages', 'idx_msg_sender_receiver')) {
                $table->index(['sender_id', 'receiver_id'], 'idx_msg_sender_receiver');
            }
        });

        // 4. Compound index for verification_codes (lookup by email + validity)
        Schema::table('verification_codes', function (Blueprint $table) {
            if (!$this->indexExists('verification_codes', 'idx_vc_email_used_expires')) {
                $table->index(['email', 'used', 'expires_at'], 'idx_vc_email_used_expires');
            }
        });

        // 5. Index for chat_messages.session_id (if column exists)
        if (Schema::hasColumn('chat_messages', 'session_id')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                if (!$this->indexExists('chat_messages', 'idx_chatmsg_session_id')) {
                    $table->index('session_id', 'idx_chatmsg_session_id');
                }
            });
        }

        // 6. Compound index for feedback rate limiting
        if (Schema::hasTable('feedback')) {
            Schema::table('feedback', function (Blueprint $table) {
                if (!$this->indexExists('feedback', 'idx_feedback_user_created')) {
                    $table->index(['user_id', 'created_at'], 'idx_feedback_user_created');
                }
            });
        }
    }

    public function down(): void
    {
        // Safely drop all indexes added by this migration
        Schema::table('appointments', function (Blueprint $table) {
            if ($this->indexExists('appointments', 'idx_apt_date_time_status')) {
                $table->dropIndex('idx_apt_date_time_status');
            }
            if ($this->indexExists('appointments', 'idx_apt_user_status')) {
                $table->dropIndex('idx_apt_user_status');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if ($this->indexExists('payments', 'payments_appointment_id_unique')) {
                $table->dropUnique('payments_appointment_id_unique');
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            if ($this->indexExists('messages', 'idx_msg_sender_receiver')) {
                $table->dropIndex('idx_msg_sender_receiver');
            }
        });

        Schema::table('verification_codes', function (Blueprint $table) {
            if ($this->indexExists('verification_codes', 'idx_vc_email_used_expires')) {
                $table->dropIndex('idx_vc_email_used_expires');
            }
        });

        if (Schema::hasColumn('chat_messages', 'session_id')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                if ($this->indexExists('chat_messages', 'idx_chatmsg_session_id')) {
                    $table->dropIndex('idx_chatmsg_session_id');
                }
            });
        }

        if (Schema::hasTable('feedback')) {
            Schema::table('feedback', function (Blueprint $table) {
                if ($this->indexExists('feedback', 'idx_feedback_user_created')) {
                    $table->dropIndex('idx_feedback_user_created');
                }
            });
        }
    }
};
