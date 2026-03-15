<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates tables for chatbot knowledge base and embeddings
     */
    public function up(): void
    {
        // Knowledge base table for RAG
        Schema::create('chatbot_knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->index(); // services, faq, policy, etc.
            $table->string('title', 255);
            $table->text('content');
            $table->json('embedding')->nullable(); // Vector embedding (JSON for SQLite compatibility)
            $table->json('metadata')->nullable(); // Additional metadata
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['category', 'is_active']);
            // $table->fullText(['title', 'content']); // For fallback keyword search
        });

        // Conversation embeddings for semantic memory
        Schema::create('chatbot_conversation_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id', 100)->unique();
            $table->text('summary');
            $table->json('embedding')->nullable();
            $table->timestamps();
        });

        // User preferences for personalization
        if (!Schema::hasTable('user_preferences')) {
            Schema::create('user_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->json('chatbot_preferences')->nullable();
                $table->json('notification_preferences')->nullable();
                $table->string('preferred_language', 10)->default('en');
                $table->string('timezone', 50)->nullable();
                $table->timestamps();
                
                $table->unique('user_id');
            });
        } else {
            // Add chatbot_preferences column if it doesn't exist
            if (!Schema::hasColumn('user_preferences', 'chatbot_preferences')) {
                Schema::table('user_preferences', function (Blueprint $table) {
                    $table->json('chatbot_preferences')->nullable()->after('user_id');
                });
            }
        }

        // Enhance chatbot_conversations table
        if (Schema::hasTable('chatbot_conversations')) {
            Schema::table('chatbot_conversations', function (Blueprint $table) {
                if (!Schema::hasColumn('chatbot_conversations', 'personality')) {
                    $table->string('personality', 20)->default('professional')->after('status');
                }
                if (!Schema::hasColumn('chatbot_conversations', 'llm_provider')) {
                    $table->string('llm_provider', 20)->nullable()->after('personality');
                }
                if (!Schema::hasColumn('chatbot_conversations', 'total_tokens_used')) {
                    $table->integer('total_tokens_used')->default(0)->after('llm_provider');
                }
            });
        }

        // Enhance chat_messages table for streaming metadata
        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('chat_messages', 'metadata')) {
                    $table->json('metadata')->nullable()->after('source');
                }
                if (!Schema::hasColumn('chat_messages', 'tokens_used')) {
                    $table->integer('tokens_used')->nullable()->after('metadata');
                }
                if (!Schema::hasColumn('chat_messages', 'llm_provider')) {
                    $table->string('llm_provider', 20)->nullable()->after('tokens_used');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_knowledge_base');
        Schema::dropIfExists('chatbot_conversation_embeddings');
        
        // Don't drop user_preferences as it might be used elsewhere
        
        if (Schema::hasTable('chatbot_conversations')) {
            Schema::table('chatbot_conversations', function (Blueprint $table) {
                $columns = ['personality', 'llm_provider', 'total_tokens_used'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('chatbot_conversations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $columns = ['metadata', 'tokens_used', 'llm_provider'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('chat_messages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
