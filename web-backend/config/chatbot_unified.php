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
        // Primary provider: claude or ollama
        'primary_provider' => env('LLM_PRIMARY_PROVIDER', 'claude'),
        
        // Claude (Anthropic)
        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-3-sonnet-20240229'),
            'max_tokens' => env('CLAUDE_MAX_TOKENS', 2048),
            'temperature' => env('CLAUDE_TEMPERATURE', 0.7),
        ],
        
        // Ollama (Self-hosted)
        'ollama' => [
            'enabled' => env('USE_OLLAMA_LLM', false),
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'mistral'),
        ],
        
        // OpenAI (Alternative)
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
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
        'ollama_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
        
        // OpenAI embeddings (paid, cloud)
        'openai_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        
        // Search settings
        'similarity_threshold' => env('EMBEDDING_SIMILARITY_THRESHOLD', 0.5),
        'max_results' => env('EMBEDDING_MAX_RESULTS', 5),
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
        'max_history' => env('CHATBOT_MAX_HISTORY', 10),
        
        // Rate limiting
        'rate_limit' => [
            'messages_per_minute' => 10,
            'messages_per_conversation' => 100,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | System Prompt (Simplified)
    |--------------------------------------------------------------------------
    | The core instructions for the AI. Keep it simple and direct.
    */
    'system_prompt' => <<<PROMPT
You are a helpful assistant for a legal services office.

CORE RULES:
1. Answer ONLY what you know from the provided data
2. If uncertain, ask for clarification
3. Never guess, assume, or fabricate information
4. Be concise and professional
5. Guide users to perform actions themselves - you cannot perform actions on their behalf

You help with:
- Appointments and scheduling
- Services and pricing information
- Payment and refund inquiries
- General office information

If a question is outside your scope, politely redirect to appropriate resources.
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
    ],
];
