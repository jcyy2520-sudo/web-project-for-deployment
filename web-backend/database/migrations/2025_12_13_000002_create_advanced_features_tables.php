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
        // Conversation threads for parallel conversations
        Schema::create('conversation_threads', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id')->unique();
            $table->uuid('session_id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('topic')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('status', ['active', 'paused', 'archived', 'completed'])->default('active');
            $table->boolean('is_pinned')->default(false);
            $table->json('tags')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'last_activity_at']);
        });

        // Workflow executions
        Schema::create('workflow_executions', function (Blueprint $table) {
            $table->id();
            $table->uuid('workflow_id')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('workflow_name');
            $table->json('steps')->nullable();
            $table->json('context')->nullable();
            $table->json('results')->nullable();
            $table->enum('status', ['pending', 'executing', 'completed', 'failed', 'rolled_back'])->default('pending');
            $table->string('error_message')->nullable();
            $table->string('failed_step')->nullable();
            $table->integer('total_steps')->default(0);
            $table->integer('completed_steps')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['workflow_name', 'created_at']);
        });

        // User long-term memory
        Schema::create('user_long_term_memory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('key');
            $table->text('value');
            $table->string('category')->nullable();
            $table->integer('access_count')->default(0);
            $table->float('relevance_score')->default(0.5);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'key']);
            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'expires_at']);
        });

        // Conversation summaries for context
        Schema::create('conversation_summaries', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('summary');
            $table->json('key_points')->nullable();
            $table->json('topics')->nullable();
            $table->json('entities')->nullable();
            $table->integer('message_count');
            $table->integer('tokens_used')->default(0);
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->default('neutral');
            $table->float('sentiment_score')->default(0);
            $table->timestamp('summarized_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
        });

        // Action audit trail
        Schema::create('action_audit_trail', function (Blueprint $table) {
            $table->id();
            $table->uuid('audit_id')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('action_name');
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable();
            $table->json('action_data')->nullable();
            $table->json('changes')->nullable();
            $table->json('permissions_checked')->nullable();
            $table->boolean('was_allowed')->default(true);
            $table->string('denial_reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'action_name']);
            $table->index(['user_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
        });

        // Chatbot error tracking
        Schema::create('chatbot_errors', function (Blueprint $table) {
            $table->id();
            $table->uuid('error_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('error_type');
            $table->text('error_message');
            $table->text('stack_trace')->nullable();
            $table->json('context')->nullable();
            $table->string('service_name')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('resolved')->default(false);
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            
            $table->index(['error_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['severity', 'resolved']);
        });

        // User satisfaction tracking
        Schema::create('user_satisfaction_ratings', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('rating')->comment('1-5 rating');
            $table->text('feedback')->nullable();
            $table->json('rating_categories')->nullable(); // Breakdown by category
            $table->boolean('would_recommend')->nullable();
            $table->string('improvement_area')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['rating', 'created_at']);
            $table->foreign('conversation_id')->references('conversation_id')->on('chatbot_conversations')->onDelete('cascade');
        });

        // Performance metrics snapshot
        Schema::create('performance_metrics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('metric_name');
            $table->float('value');
            $table->json('breakdown')->nullable();
            $table->string('period'); // 'hour', 'day', 'week', 'month'
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamps();
            
            $table->index(['metric_name', 'period']);
            $table->index(['snapshot_at']);
        });

        // WebSocket connections tracking
        Schema::create('websocket_connections', function (Blueprint $table) {
            $table->id();
            $table->string('connection_id')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('session_id');
            $table->string('ip_address')->nullable();
            $table->json('subscriptions')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'connected_at']);
            $table->index(['session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('websocket_connections');
        Schema::dropIfExists('performance_metrics_snapshots');
        Schema::dropIfExists('user_satisfaction_ratings');
        Schema::dropIfExists('chatbot_errors');
        Schema::dropIfExists('action_audit_trail');
        Schema::dropIfExists('conversation_summaries');
        Schema::dropIfExists('user_long_term_memory');
        Schema::dropIfExists('workflow_executions');
        Schema::dropIfExists('conversation_threads');
    }
};
