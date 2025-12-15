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
        // System Metrics table
        Schema::create('system_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamp('timestamp');
            $table->float('cpu_usage')->default(0); // Percentage
            $table->bigInteger('memory_usage')->default(0); // Bytes
            $table->bigInteger('memory_total')->default(0); // Bytes
            $table->bigInteger('disk_usage')->default(0); // Bytes
            $table->bigInteger('disk_total')->default(0); // Bytes
            $table->bigInteger('disk_free')->default(0); // Bytes
            $table->float('load_average_1min')->default(0);
            $table->float('load_average_5min')->default(0);
            $table->float('load_average_15min')->default(0);
            $table->integer('processes_running')->default(0);
            $table->bigInteger('network_in_bytes')->default(0);
            $table->bigInteger('network_out_bytes')->default(0);
            $table->integer('database_connections')->default(0);
            $table->float('database_size_mb')->default(0);
            $table->float('cache_memory_usage_mb')->default(0);
            $table->integer('active_sessions')->default(0);
            $table->integer('pending_jobs')->default(0);
            $table->integer('failed_jobs')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('timestamp');
            $table->index('created_at');
        });

        // Security Events table
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type'); // rate_limit_exceeded, ip_blocked, suspicious_activity, etc.
            $table->ipAddress('ip_address');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('method')->nullable();
            $table->integer('status_code')->nullable();
            $table->integer('request_count_per_minute')->default(0);
            $table->boolean('is_suspicious')->default(false);
            $table->float('risk_score')->default(0);
            $table->json('details')->nullable();
            $table->string('action_taken')->nullable(); // blocked, logged, alerted, etc.
            $table->timestamp('blocked_until')->nullable();
            $table->timestamps();
            $table->index('ip_address');
            $table->index('event_type');
            $table->index('is_suspicious');
            $table->index('created_at');
            $table->index('blocked_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_metrics');
        Schema::dropIfExists('security_events');
    }
};
