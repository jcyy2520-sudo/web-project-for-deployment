<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            if (DB::getDriverName() === 'sqlite') {
                $indexes = DB::select("PRAGMA index_list('{$table}')");
                foreach ($indexes as $index) {
                    if ($index->name === $indexName) return true;
                }
                return false;
            }
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Add performance indexes for faster queries across the application.
     */
    public function up(): void
    {
        // Appointments table indexes
        Schema::table('appointments', function (Blueprint $table) {
            // Index for status queries (very common)
            if (!$this->indexExists('appointments', 'appointments_status_index')) {
                $table->index('status', 'appointments_status_index');
            }
            // Index for date queries
            if (!$this->indexExists('appointments', 'appointments_appointment_date_index')) {
                $table->index('appointment_date', 'appointments_appointment_date_index');
            }
            // Composite index for date + status (common filter combination)
            if (!$this->indexExists('appointments', 'appointments_date_status_index')) {
                $table->index(['appointment_date', 'status'], 'appointments_date_status_index');
            }
            // Composite index for user + status
            if (!$this->indexExists('appointments', 'appointments_user_status_index')) {
                $table->index(['user_id', 'status'], 'appointments_user_status_index');
            }
            // Index for payment_status
            if (!$this->indexExists('appointments', 'appointments_payment_status_index')) {
                $table->index('payment_status', 'appointments_payment_status_index');
            }
            // Index for payment_date
            if (!$this->indexExists('appointments', 'appointments_payment_date_index')) {
                $table->index('payment_date', 'appointments_payment_date_index');
            }
        });

        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            // Index for role queries
            if (!$this->indexExists('users', 'users_role_index')) {
                $table->index('role', 'users_role_index');
            }
            // Index for is_active
            if (!$this->indexExists('users', 'users_is_active_index')) {
                $table->index('is_active', 'users_is_active_index');
            }
            // Composite for role + is_active
            if (!$this->indexExists('users', 'users_role_is_active_index')) {
                $table->index(['role', 'is_active'], 'users_role_is_active_index');
            }
        });

        // Action logs indexes (if table exists)
        if (Schema::hasTable('action_logs')) {
            Schema::table('action_logs', function (Blueprint $table) {
                // Index for action (the actual column name)
                if (!$this->indexExists('action_logs', 'action_logs_action_index')) {
                    $table->index('action', 'action_logs_action_index');
                }
                // Index for created_at
                if (!$this->indexExists('action_logs', 'action_logs_created_at_index')) {
                    $table->index('created_at', 'action_logs_created_at_index');
                }
                // Index for user_id
                if (!$this->indexExists('action_logs', 'action_logs_user_id_index')) {
                    $table->index('user_id', 'action_logs_user_id_index');
                }
            });
        }

        // Refunds table indexes (if table exists)
        if (Schema::hasTable('refunds')) {
            Schema::table('refunds', function (Blueprint $table) {
                // Index for status
                if (!$this->indexExists('refunds', 'refunds_status_index')) {
                    $table->index('status', 'refunds_status_index');
                }
                // Index for requested_by
                if (!$this->indexExists('refunds', 'refunds_requested_by_index')) {
                    $table->index('requested_by', 'refunds_requested_by_index');
                }
                // Index for created_at
                if (!$this->indexExists('refunds', 'refunds_created_at_index')) {
                    $table->index('created_at', 'refunds_created_at_index');
                }
                // Index for appointment_id
                if (!$this->indexExists('refunds', 'refunds_appointment_id_index')) {
                    $table->index('appointment_id', 'refunds_appointment_id_index');
                }
            });
        }

        // Services table indexes (if table exists)
        if (Schema::hasTable('services')) {
            Schema::table('services', function (Blueprint $table) {
                // Index for is_active
                if (!$this->indexExists('services', 'services_is_active_index')) {
                    $table->index('is_active', 'services_is_active_index');
                }
            });
        }

        // Messages table indexes (if table exists)
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                // Index for sender_id
                if (!$this->indexExists('messages', 'messages_sender_id_index')) {
                    $table->index('sender_id', 'messages_sender_id_index');
                }
                // Index for receiver_id
                if (!$this->indexExists('messages', 'messages_receiver_id_index')) {
                    $table->index('receiver_id', 'messages_receiver_id_index');
                }
                // Index for read status
                if (!$this->indexExists('messages', 'messages_read_index')) {
                    $table->index('read', 'messages_read_index');
                }
            });
        }

        // Unavailable dates indexes (if table exists)
        if (Schema::hasTable('unavailable_dates')) {
            Schema::table('unavailable_dates', function (Blueprint $table) {
                // Index for date
                if (!$this->indexExists('unavailable_dates', 'unavailable_dates_date_index')) {
                    $table->index('date', 'unavailable_dates_date_index');
                }
            });
        }
    }

    /**
     * Reverse the migrations - safely drop indexes if they exist.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if ($this->indexExists('appointments', 'appointments_status_index')) {
                $table->dropIndex('appointments_status_index');
            }
            if ($this->indexExists('appointments', 'appointments_appointment_date_index')) {
                $table->dropIndex('appointments_appointment_date_index');
            }
            if ($this->indexExists('appointments', 'appointments_date_status_index')) {
                $table->dropIndex('appointments_date_status_index');
            }
            if ($this->indexExists('appointments', 'appointments_user_status_index')) {
                $table->dropIndex('appointments_user_status_index');
            }
            if ($this->indexExists('appointments', 'appointments_payment_status_index')) {
                $table->dropIndex('appointments_payment_status_index');
            }
            if ($this->indexExists('appointments', 'appointments_payment_date_index')) {
                $table->dropIndex('appointments_payment_date_index');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'users_role_index')) {
                $table->dropIndex('users_role_index');
            }
            if ($this->indexExists('users', 'users_is_active_index')) {
                $table->dropIndex('users_is_active_index');
            }
            if ($this->indexExists('users', 'users_role_is_active_index')) {
                $table->dropIndex('users_role_is_active_index');
            }
        });
    }
};
