<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('job_name');
            $table->string('job_class');
            $table->string('queue')->default('default');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'retried'])->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->text('payload')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('output')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('will_retry_at')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('job_name');
            $table->index('queue');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_metrics');
    }
};
