<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add status, metadata, and integrity_hash columns to action_logs
     * for comprehensive error tracking and tamper detection.
     */
    public function up(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('action_logs', 'status')) {
                $table->string('status', 20)->default('success')
                    ->after('user_agent')
                    ->comment('success, failed, error')
                    ->index();
            }

            if (!Schema::hasColumn('action_logs', 'metadata')) {
                $table->json('metadata')->nullable()
                    ->after('status')
                    ->comment('Extra context: old/new values, error details');
            }

            if (!Schema::hasColumn('action_logs', 'integrity_hash')) {
                $table->string('integrity_hash', 64)->nullable()
                    ->after('metadata')
                    ->comment('HMAC-SHA256 tamper-detection hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            if (Schema::hasColumn('action_logs', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('action_logs', 'metadata')) {
                $table->dropColumn('metadata');
            }
            if (Schema::hasColumn('action_logs', 'integrity_hash')) {
                $table->dropColumn('integrity_hash');
            }
        });
    }
};
