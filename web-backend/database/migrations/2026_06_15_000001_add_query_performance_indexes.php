<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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

    public function up(): void
    {
        // chat_messages: session_id used by guest chatbot queries
        if (Schema::hasTable('chat_messages') && Schema::hasColumn('chat_messages', 'session_id')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                if (!$this->indexExists('chat_messages', 'chat_messages_session_id_index')) {
                    $table->index('session_id', 'chat_messages_session_id_index');
                }
            });
        }

        // audit_logs: ip_address used in suspicious IP groupBy queries
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (!$this->indexExists('audit_logs', 'audit_logs_ip_address_index')) {
                    $table->index('ip_address', 'audit_logs_ip_address_index');
                }
                if (!$this->indexExists('audit_logs', 'audit_logs_action_created_at_index')) {
                    $table->index(['action', 'created_at'], 'audit_logs_action_created_at_index');
                }
            });
        }

        // frontend_error_logs: status used in stats queries
        if (Schema::hasTable('frontend_error_logs') && Schema::hasColumn('frontend_error_logs', 'status')) {
            Schema::table('frontend_error_logs', function (Blueprint $table) {
                if (!$this->indexExists('frontend_error_logs', 'frontend_error_logs_status_index')) {
                    $table->index('status', 'frontend_error_logs_status_index');
                }
            });
        }

        // job_metrics: composite for stuck job queries
        if (Schema::hasTable('job_metrics')) {
            Schema::table('job_metrics', function (Blueprint $table) {
                if (!$this->indexExists('job_metrics', 'job_metrics_status_started_at_index')) {
                    $table->index(['status', 'started_at'], 'job_metrics_status_started_at_index');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                if ($this->indexExists('chat_messages', 'chat_messages_session_id_index')) {
                    $table->dropIndex('chat_messages_session_id_index');
                }
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if ($this->indexExists('audit_logs', 'audit_logs_ip_address_index')) {
                    $table->dropIndex('audit_logs_ip_address_index');
                }
                if ($this->indexExists('audit_logs', 'audit_logs_action_created_at_index')) {
                    $table->dropIndex('audit_logs_action_created_at_index');
                }
            });
        }

        if (Schema::hasTable('frontend_error_logs')) {
            Schema::table('frontend_error_logs', function (Blueprint $table) {
                if ($this->indexExists('frontend_error_logs', 'frontend_error_logs_status_index')) {
                    $table->dropIndex('frontend_error_logs_status_index');
                }
            });
        }

        if (Schema::hasTable('job_metrics')) {
            Schema::table('job_metrics', function (Blueprint $table) {
                if ($this->indexExists('job_metrics', 'job_metrics_status_started_at_index')) {
                    $table->dropIndex('job_metrics_status_started_at_index');
                }
            });
        }
    }
};
