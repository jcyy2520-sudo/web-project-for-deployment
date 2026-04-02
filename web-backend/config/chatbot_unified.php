<?php

/**
 * Unified Chatbot Configuration
 * 
 * This is the simplified configuration for the LLM-first chatbot.
 * The core architecture is:
 * 
 * 1. EMBED: User message → Vector embedding
 * 2. RETRIEVE: Find relevant knowledge via semantic search
 * 3. AUGMENT: Combine context + history + real-time data
 * 4. GENERATE: Send to LLM for intelligent response
 */

return [
    
    /*
    |--------------------------------------------------------------------------
    | Business Information
    |--------------------------------------------------------------------------
    | Core business details used in chatbot responses
    */
    'business' => [
        'name' => env('BUSINESS_NAME', 'Peejayy De Guzman Legal'),
        'email' => env('BUSINESS_EMAIL', 'peejaydeguzmanlegal@gmail.com'),
        'phone' => env('BUSINESS_PHONE', '09765075274'),
        'address' => env('BUSINESS_ADDRESS', '233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | LLM Configuration
    |--------------------------------------------------------------------------
    | Primary AI provider settings
    */
    'llm' => [
        // Primary provider: github_gpt5, gemini, openai, or ollama
        'primary_provider' => env('LLM_PRIMARY_PROVIDER', 'github_gpt5'),
        
        // Provider order for fallbacks
        'provider_order' => env('LLM_PROVIDER_ORDER', 'github_gpt5,gemini,openai,mistral,groq'),
        
        // HTTP request timeout in seconds (shortened for faster failover)
        'request_timeout' => env('LLM_REQUEST_TIMEOUT', 60),
        
        // Streaming timeout (longer for streaming)
        'streaming_timeout' => env('LLM_STREAMING_TIMEOUT', 300),
        'streaming_max_tokens' => env('LLM_STREAMING_MAX_TOKENS', 4096),
        
        // Generation parameters
        'temperature' => env('LLM_TEMPERATURE', 0.3),
        'top_p' => env('LLM_TOP_P', 0.9),
        
        // Gemini (Primary)
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-pro-latest'),
            'max_tokens' => env('GEMINI_MAX_TOKENS', 4096),
            'temperature' => env('GEMINI_TEMPERATURE', 0.3),
        ],

        // GitHub Models (GPT-5) - Secondary
        'github_gpt5' => [
            'api_key' => env('GITHUB_TOKEN'),
            'model' => env('GITHUB_GPT5_MODEL', 'openai/gpt-5'),
            'base_url' => env('GITHUB_ENDPOINT', 'https://models.github.ai/inference'),
            'max_tokens' => env('GPT5_MAX_TOKENS', 4096),
            'temperature' => env('GPT5_TEMPERATURE', 0.5),
        ],
        
        // Ollama (Self-hosted)
        'ollama' => [
            'enabled' => env('USE_OLLAMA_LLM', false),
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'mistral'),
        ],
        // Mistral (Fallback)
        'mistral' => [
            'api_key' => env('MISTRAL_API_KEY'),
            'model' => env('MISTRAL_MODEL', 'mistral-large-latest'),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Embedding Configuration
    |--------------------------------------------------------------------------
    | Vector embedding service settings for semantic search
    */
    'embeddings' => [
        // Use Ollama for embeddings (free, local)
        'use_ollama' => env('USE_OLLAMA_EMBEDDINGS', true),
        
        // API URLs
        'ollama_url' => env('OLLAMA_EMBEDDINGS_URL', 'http://localhost:11434/api/embeddings'),
        'voyage_url' => env('VOYAGE_EMBEDDINGS_URL', 'https://api.voyageai.com/v1/embeddings'),
        
        // Model names
        'ollama_model' => env('OLLAMA_EMBEDDING_MODEL', 'all-minilm'),
        'voyage_model' => env('VOYAGE_EMBEDDING_MODEL', 'voyage-3'),
        
        // Timeouts and caching
        'request_timeout' => env('EMBEDDING_REQUEST_TIMEOUT', 30),
        'cache_ttl' => env('EMBEDDING_CACHE_TTL', 86400),
        
        // Search tuning
        'similarity_threshold' => env('EMBEDDING_SIMILARITY_THRESHOLD', 0.3),
        'max_results' => env('EMBEDDING_MAX_RESULTS', 8),
        'bm25_weight' => env('EMBEDDING_BM25_WEIGHT', 0.4),
        'vector_weight' => env('EMBEDDING_VECTOR_WEIGHT', 0.6),
        
        // Chunking
        'max_chunk_size' => env('EMBEDDING_MAX_CHUNK_SIZE', 500),
        'chunk_overlap' => env('EMBEDDING_CHUNK_OVERLAP', 50),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Knowledge Base
    |--------------------------------------------------------------------------
    | Settings for RAG (Retrieval-Augmented Generation)
    */
    'knowledge_base' => [
        // Path to knowledge bundle JSON
        'bundle_path' => env('KNOWLEDGE_BUNDLE_PATH', '../chatbot_knowledge/chatbot_knowledge_bundle_with_summaries.json'),
        
        // Chunk settings for document indexing
        'chunk_size' => 500,
        'chunk_overlap' => 50,
        
        // Cache TTL for embeddings (24 hours)
        'cache_ttl' => 86400,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Conversation Settings
    |--------------------------------------------------------------------------
    */
    'conversation' => [
        // Max messages to include in context
        'max_history' => env('CHATBOT_MAX_HISTORY', 20),
        
        // Similarity threshold for context retrieval
        'similarity_threshold' => env('CHATBOT_SIMILARITY_THRESHOLD', 0.35),
        
        // Maximum context documents to include
        'max_context_docs' => env('CHATBOT_MAX_CONTEXT_DOCS', 8),
        
        // Cache TTL for conversation data (minutes)
        'cache_ttl_minutes' => env('CHATBOT_CACHE_TTL_MINUTES', 10),
        
        // Rate limiting
        'rate_limit' => [
            'messages_per_minute' => 15,
            'messages_per_conversation' => 200,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | System Prompt (Dynamic — No Hard-Coded Rules)
    |--------------------------------------------------------------------------
    | The system prompt is now built ENTIRELY at runtime by
    | DynamicSystemPromptService. This config section exists only as
    | fallback reference. The actual prompt is dynamically assembled
    | from: database schema, API endpoints, business rules, user context,
    | conversation memory, feedback insights, and RAG-retrieved knowledge.
    |
    | To customize behavior, modify DynamicSystemPromptService.php
    | rather than editing this static text.
    */
    'system_prompt' => <<<PROMPT
You are a fully autonomous, adaptive AI assistant. Your system prompt is built dynamically at runtime — this text is only a reference fallback.

CORE RULES:
1. Answer ONLY from provided data — NEVER guess or fabricate. If you don't have verifiable data, say so.
2. If uncertain, ask for clarification before answering. It is always better to ask than to guess.
3. When agent_mode is enabled, execute actions on behalf of users using available tools. Always confirm destructive actions.
4. Adapt language to match the user (English, Filipino, Taglish)
5. Handle messy inputs gracefully: typos, slang, SMS-speak, broken grammar
6. Focus on user's INTENT, not the quality of their typing
7. Cite specific data points when answering (appointment IDs, dates, amounts) — never give vague answers when real data is available.
8. When corrected, acknowledge the correction and try a completely different approach.

All responses are dynamically generated based on live system state, knowledge base, user role, and conversation context. No hard-coded responses exist.
PROMPT,
    
    /*
    |--------------------------------------------------------------------------
    | Feedback System
    |--------------------------------------------------------------------------
    | Settings for the feedback loop and continuous improvement
    */
    'feedback' => [
        // Enable feedback collection
        'enabled' => true,
        
        // Categories for feedback classification
        'categories' => [
            'wrong_info' => 'Information was incorrect',
            'outdated' => 'Information was outdated',
            'unclear' => 'Response was unclear',
            'incomplete' => 'Response was incomplete',
            'off_topic' => 'Response was off-topic',
            'helpful' => 'Response was very helpful',
            'other' => 'Other feedback',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Safety & Content Filtering
    |--------------------------------------------------------------------------
    */
    'safety' => [
        // Block harmful content patterns
        'harmful_patterns' => [
            'hack', 'exploit', 'vulnerability', 'inject', 'sql injection',
            'delete all', 'drop table', 'rm -rf',
        ],
        
        // Out of scope patterns (politely decline)
        'out_of_scope_patterns' => [
            'tell me a joke', 'write a poem', 'play a game',
            'ignore your instructions', 'pretend you are',
        ],

        // Strict refusal message for out-of-scope/security queries
        'refusal_message' => "This question is outside the scope of this system. I can only assist with topics related to this system.",
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    | Toggle new features on/off via .env variables.
    | All default to false for safe rollout.
    */
    'features' => [
        'cache_static_prompt'  => env('CHATBOT_CACHE_STATIC_PROMPT', false),
        'memory_summary'       => env('CHATBOT_MEMORY_SUMMARY', false),
        'fallback_model'       => env('CHATBOT_FALLBACK_MODEL', false),
        'guard_service'        => env('CHATBOT_USE_GUARD', false),
        'data_ownership'       => env('CHATBOT_ENFORCE_DATA_OWNERSHIP', true),
        'context_overflow'     => env('CHATBOT_PREVENT_CONTEXT_OVERFLOW', false),
        'confidence_score'     => env('CHATBOT_CONFIDENCE_SCORE', false),
        'self_check'           => env('CHATBOT_SELF_CHECK', false),
        'reranker'             => env('CHATBOT_USE_RERANKER', false),
        'long_term_memory'     => env('CHATBOT_LONG_TERM_MEMORY', false),
        'streaming'            => env('CHATBOT_ENABLE_STREAMING', false),
        'analytics'            => env('CHATBOT_ANALYTICS_ENABLED', false),
        'agent_mode'           => env('CHATBOT_AGENT_MODE', false),
        'continuous_learning'  => env('CHATBOT_CONTINUOUS_LEARNING', false),
        'intelligent_fallback' => env('CHATBOT_INTELLIGENT_FALLBACK', false),
        'operational_decisions' => env('CHATBOT_OPERATIONAL_DECISIONS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Failover Configuration
    |--------------------------------------------------------------------------
    | Primary and fallback models for LLM generation.
    | Used when 'fallback_model' feature flag is enabled.
    */
    'models' => [
        'primary' => env('CHATBOT_PRIMARY_MODEL', 'meta-llama/Llama-3.3-70B-Instruct'),
        'fallback' => env('CHATBOT_FALLBACK_MODEL_NAME', 'meta-llama/Llama-3.2-3B-Instruct'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reranker Configuration
    |--------------------------------------------------------------------------
    | Cross-encoder reranking for RAG results.
    | Used when 'reranker' feature flag is enabled.
    */
    'reranker' => [
        'model' => env('CHATBOT_RERANKER_MODEL', 'cross-encoder/ms-marco-MiniLM-L-6-v2'),
        'top_k' => env('CHATBOT_RERANKER_TOP_K', 3),
        'candidates' => env('CHATBOT_RERANKER_CANDIDATES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'retention_days' => env('CHATBOT_ANALYTICS_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Overflow Protection
    |--------------------------------------------------------------------------
    */
    'context' => [
        'max_tokens' => env('CHATBOT_MAX_CONTEXT_TOKENS', 16000),
        'overflow_threshold' => env('CHATBOT_CONTEXT_OVERFLOW_THRESHOLD', 0.85),
    ],

    /*
    |--------------------------------------------------------------------------
    | Long-Term Memory
    |--------------------------------------------------------------------------
    */
    'long_term_memory' => [
        'max_past_summaries' => env('CHATBOT_MAX_PAST_SUMMARIES', 5),
        'summary_min_messages' => env('CHATBOT_SUMMARY_MIN_MESSAGES', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Mode Configuration
    |--------------------------------------------------------------------------
    | When agent_mode is enabled, the chatbot can execute actions on behalf of
    | users via tool-calling (ReAct reasoning loop). Destructive actions require
    | explicit user confirmation before execution.
    */
    'agent' => [
        'max_reasoning_steps' => env('CHATBOT_AGENT_MAX_STEPS', 5),
        'confirmation_timeout_seconds' => env('CHATBOT_AGENT_CONFIRM_TIMEOUT', 300),
        'max_tool_calls_per_message' => env('CHATBOT_AGENT_MAX_TOOLS', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Booking Configuration
    |--------------------------------------------------------------------------
    | Constraints for appointment bookings made via the chatbot.
    */
    'booking' => [
        'daily_limit_per_user' => env('CHATBOT_BOOKING_DAILY_LIMIT', 3),
        'working_hour_start' => env('CHATBOT_BOOKING_WORK_START', 8),
        'working_hour_end' => env('CHATBOT_BOOKING_WORK_END', 17),
        'lunch_break_start' => env('CHATBOT_BOOKING_LUNCH_START', 12),
        'lunch_break_end' => env('CHATBOT_BOOKING_LUNCH_END', 13),
        'slot_interval_minutes' => env('CHATBOT_BOOKING_SLOT_INTERVAL', 30),
        'default_slot_capacity' => env('CHATBOT_BOOKING_DEFAULT_CAPACITY', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    | TTL values for chatbot data caching (in seconds).
    */
    'cache' => [
        'ttl' => env('CHATBOT_CACHE_TTL', 300),
        'critical_ttl' => env('CHATBOT_CRITICAL_CACHE_TTL', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    | Role hierarchy and security-related settings.
    */
    'security' => [
        'role_hierarchy' => [
            'guest'   => 0,
            'client'  => 1,
            'staff'   => 2,
            'cashier' => 3,
            'admin'   => 4,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Action Handler Configuration
    |--------------------------------------------------------------------------
    | Role permissions and destructive action definitions for ChatbotActionHandler.
    */
    'action_handler' => [
        'destructive_actions' => [
            'appointment' => ['approve', 'decline', 'cancel', 'complete'],
            'payment'     => ['process'],
            'refund'      => ['approve', 'decline', 'process', 'request'],
            'user'        => ['disable'],
        ],
        'role_permissions' => [
            'client' => [
                'appointment' => ['view', 'cancel', 'reschedule'],
                'payment' => ['view'],
                'refund' => ['view', 'request'],
                'notification' => ['view'],
            ],
            'cashier' => [
                'appointment' => ['view'],
                'payment' => ['view', 'process', 'verify'],
                'refund' => ['view', 'process', 'approve'],
                'notification' => ['view', 'send'],
            ],
            'admin' => [
                'appointment' => ['view', 'approve', 'decline', 'cancel', 'complete', 'reschedule'],
                'payment' => ['view', 'process', 'verify', 'refund'],
                'refund' => ['view', 'approve', 'decline', 'process'],
                'notification' => ['view', 'send', 'broadcast'],
                'user' => ['view', 'manage', 'disable'],
                'service' => ['view', 'manage'],
                'system' => ['view', 'configure'],
            ],
            'staff' => [
                'appointment' => ['view', 'approve', 'decline', 'complete'],
                'payment' => ['view'],
                'refund' => ['view'],
                'notification' => ['view', 'send'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Continuous Learning Pipeline
    |--------------------------------------------------------------------------
    | When continuous_learning is enabled, the ML models detect drift and retrain
    | adaptively instead of only on a weekly batch schedule.
    */
    'continuous_learning' => [
        'mode' => env('CHATBOT_LEARNING_MODE', 'INCREMENTAL'), // PASSIVE, INCREMENTAL, ADAPTIVE, CONTINUOUS
        'drift_check_interval_hours' => env('CHATBOT_DRIFT_CHECK_HOURS', 6),
        'min_samples_for_retrain' => env('CHATBOT_MIN_RETRAIN_SAMPLES', 50),
        'max_days_between_retrain' => env('CHATBOT_MAX_RETRAIN_DAYS', 7),
        'accuracy_threshold' => env('CHATBOT_ACCURACY_THRESHOLD', 0.6),
        'brier_threshold' => env('CHATBOT_BRIER_THRESHOLD', 0.3),
    ],
];
