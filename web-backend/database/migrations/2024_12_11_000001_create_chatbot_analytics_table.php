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
        Schema::create('chatbot_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('conversation_id')->nullable()->index();
            $table->string('session_id')->nullable()->index();
            
            // Message tracking
            $table->text('user_message')->nullable();
            $table->text('bot_response')->nullable();
            $table->string('detected_intent')->nullable();
            $table->string('detected_language', 10)->default('en');
            $table->json('entities_extracted')->nullable();
            
            // Sentiment & Priority
            $table->string('sentiment')->default('neutral'); // positive, neutral, negative, frustrated, angry
            $table->integer('sentiment_score')->default(0);
            $table->boolean('is_priority')->default(false);
            $table->string('priority_reason')->nullable();
            
            // Performance metrics
            $table->integer('response_time_ms')->nullable();
            $table->string('response_source')->default('pattern'); // pattern, ai, fallback
            $table->float('confidence_score')->nullable();
            
            // Request status
            $table->boolean('was_successful')->default(true);
            $table->text('failure_reason')->nullable();
            $table->boolean('is_out_of_scope')->default(false);
            
            // Action tracking
            $table->string('action_type')->nullable();
            $table->boolean('action_executed')->default(false);
            $table->json('action_result')->nullable();
            
            // Abuse detection
            $table->boolean('contains_profanity')->default(false);
            $table->boolean('is_spam')->default(false);
            $table->boolean('is_rate_limited')->default(false);
            
            // User context
            $table->string('user_role')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            
            $table->timestamps();
            
            // Indexes for analytics queries
            $table->index(['created_at', 'user_id']);
            $table->index(['detected_intent', 'was_successful']);
            $table->index(['sentiment', 'is_priority']);
            $table->index(['is_rate_limited', 'created_at']);
        });

        Schema::create('chatbot_rate_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('conversation_id')->nullable();
            
            $table->integer('message_count')->default(0);
            $table->timestamp('window_start')->useCurrent();
            $table->timestamp('window_end')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('blocked_until')->nullable();
            $table->string('block_reason')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'window_start']);
            $table->index(['session_id', 'window_start']);
            $table->index(['ip_address', 'window_start']);
        });
        
        Schema::create('chatbot_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id')->nullable();
            
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->string('detected_language', 10)->default('en');
            $table->string('primary_intent')->nullable();
            
            // Conversation context
            $table->json('context_data')->nullable();
            $table->integer('message_count')->default(0);
            $table->integer('user_message_count')->default(0);
            $table->integer('bot_message_count')->default(0);
            
            // Sentiment tracking
            $table->string('overall_sentiment')->default('neutral');
            $table->float('average_sentiment_score')->default(0);
            
            // Status
            $table->string('status')->default('active'); // active, rate_limited, completed, abandoned
            $table->boolean('was_rate_limited')->default(false);
            $table->boolean('requires_human_follow_up')->default(false);
            
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['last_activity_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_conversations');
        Schema::dropIfExists('chatbot_rate_limits');
        Schema::dropIfExists('chatbot_analytics');
    }
};
