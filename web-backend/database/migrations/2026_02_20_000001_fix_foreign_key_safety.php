<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix dangerous cascade delete on staff_id and add payment uniqueness constraint.
     * 
     * Issues fixed:
     * 1. staff_id ON DELETE CASCADE deletes client appointments when staff user is deleted
     *    → Changed to ON DELETE SET NULL (staff_id is already nullable)
     * 2. action_logs.user_id ON DELETE CASCADE destroys audit trail
     *    → Changed to ON DELETE SET NULL with nullable user_id
     * 3. No unique constraint on payments.appointment_id allows duplicate payments
     *    → Added unique index as defense-in-depth
     */
    public function up(): void
    {
        // Fix 1: Change appointments.staff_id from CASCADE to SET NULL
        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'staff_id')) {
            try {
                Schema::table('appointments', function (Blueprint $table) {
                    $table->dropForeign(['staff_id']);
                });
                Schema::table('appointments', function (Blueprint $table) {
                    $table->foreign('staff_id')
                        ->references('id')->on('users')
                        ->onDelete('set null');
                });
            } catch (\Exception $e) {
                // Foreign key may not exist in some environments
                \Log::warning('Could not alter appointments.staff_id FK: ' . $e->getMessage());
            }
        }

        // Fix 2: Change action_logs.user_id from CASCADE to SET NULL
        if (Schema::hasTable('action_logs') && Schema::hasColumn('action_logs', 'user_id')) {
            try {
                // Make user_id nullable first
                Schema::table('action_logs', function (Blueprint $table) {
                    $table->unsignedBigInteger('user_id')->nullable()->change();
                });
                Schema::table('action_logs', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
                Schema::table('action_logs', function (Blueprint $table) {
                    $table->foreign('user_id')
                        ->references('id')->on('users')
                        ->onDelete('set null');
                });
            } catch (\Exception $e) {
                \Log::warning('Could not alter action_logs.user_id FK: ' . $e->getMessage());
            }
        }

        // Fix 3: Add unique constraint on payments.appointment_id to prevent duplicate payments
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'appointment_id')) {
            $exists = false;
            try {
                if (DB::getDriverName() === 'sqlite') {
                    $indexes = DB::select("PRAGMA index_list('payments')");
                    foreach ($indexes as $index) {
                        if ($index->name === 'payments_appointment_id_unique') {
                            $exists = true;
                            break;
                        }
                    }
                } else {
                    $exists = collect(DB::select("SHOW INDEX FROM payments WHERE Key_name = 'payments_appointment_id_unique'"))->isNotEmpty();
                }
            } catch (\Exception $e) {
                // Ignore error if schema doesn't exist yet
            }
            if (!$exists) {
                try {
                    Schema::table('payments', function (Blueprint $table) {
                        $table->unique('appointment_id', 'payments_appointment_id_unique');
                    });
                } catch (\Exception $e) {
                    \Log::warning('Could not add unique index on payments.appointment_id: ' . $e->getMessage());
                }
            }
        }
    }

    public function down(): void
    {
        // Revert staff_id back to CASCADE
        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'staff_id')) {
            try {
                Schema::table('appointments', function (Blueprint $table) {
                    $table->dropForeign(['staff_id']);
                });
                Schema::table('appointments', function (Blueprint $table) {
                    $table->foreign('staff_id')
                        ->references('id')->on('users')
                        ->onDelete('cascade');
                });
            } catch (\Exception $e) {
                \Log::warning('Could not revert appointments.staff_id FK: ' . $e->getMessage());
            }
        }

        // Revert action_logs
        if (Schema::hasTable('action_logs') && Schema::hasColumn('action_logs', 'user_id')) {
            try {
                Schema::table('action_logs', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
                Schema::table('action_logs', function (Blueprint $table) {
                    $table->foreign('user_id')
                        ->references('id')->on('users')
                        ->onDelete('cascade');
                });
            } catch (\Exception $e) {
                \Log::warning('Could not revert action_logs.user_id FK: ' . $e->getMessage());
            }
        }

        // Remove unique constraint on payments
        if (Schema::hasTable('payments')) {
            try {
                Schema::table('payments', function (Blueprint $table) {
                    $table->dropUnique('payments_appointment_id_unique');
                });
            } catch (\Exception $e) {
                \Log::warning('Could not drop payments unique index: ' . $e->getMessage());
            }
        }
    }
};
