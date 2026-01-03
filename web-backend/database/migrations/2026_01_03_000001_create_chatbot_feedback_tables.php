<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates tables for the chatbot feedback loop system:
     * - chatbot_interaction_logs: Logs every chatbot interaction
     * - chatbot_feedback: Stores user feedback on responses
     */
    public function up(): void
    {
        // Interaction logs - tracks every chatbot conversation
        Schema::create('chatbot_interaction_logs', function (Blueprint $table) {
            $table->id();
            $table->string('interaction_id', 36)->unique(); // UUID as string(36) for MySQL compatibility
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('conversation_id', 100)->nullable()->index(); // Limited length for index
            $table->string('session_id', 100)->nullable()->index(); // Limited length for index
            
            // Message content
            $table->text('user_message');
            $table->text('bot_response');
            
            // Intent & Analysis
            $table->string('intent_detected')->nullable();
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->json('context_sources')->nullable(); // Which knowledge base docs were used
            
            // Performance metrics
            $table->string('llm_provider')->nullable(); // claude, ollama, etc.
            $table->integer('processing_time_ms')->nullable();
            $table->string('response_source')->default('llm'); // llm, fallback, cached
            $table->boolean('was_fallback')->default(false);
            
            // Feedback tracking
            $table->boolean('has_feedback')->default(false);
            $table->tinyInteger('feedback_rating')->nullable(); // 1-5 or 0/1 for thumbs
            
            // Request metadata
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamps();
            
            // Indexes for analytics queries
            $table->index(['created_at', 'was_fallback']);
            $table->index(['user_id', 'created_at']);
        });

        // User feedback on chatbot responses
        Schema::create('chatbot_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('interaction_id', 36); // Match the interaction_logs table
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // Rating feedback
            $table->tinyInteger('rating')->nullable(); // 1-5 scale
            $table->boolean('is_helpful')->nullable(); // Simple thumbs up/down
            $table->boolean('is_correct')->nullable(); // Was the info accurate?
            
            // Correction data (for retraining)
            $table->text('correction_text')->nullable(); // User's correction
            $table->text('expected_response')->nullable(); // What user expected
            
            // Categorization
            $table->string('feedback_category', 50)->nullable(); // wrong_info, unclear, off_topic, rude, etc.
            $table->text('comments')->nullable(); // Free-form feedback
            
            // Retraining tracking
            $table->boolean('correction_applied')->default(false);
            $table->timestamp('applied_at')->nullable();
            
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
            
            // Foreign key to interaction logs
            $table->foreign('interaction_id')
                ->references('interaction_id')
                ->on('chatbot_interaction_logs')
                ->onDelete('cascade');
            
            // Indexes
            $table->index(['is_correct', 'submitted_at']);
            $table->index(['feedback_category', 'submitted_at']);
            $table->index(['correction_applied', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_feedback');
        Schema::dropIfExists('chatbot_interaction_logs');
    }
};
