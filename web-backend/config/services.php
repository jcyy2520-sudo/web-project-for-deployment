<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI/LLM Provider Configuration
    |--------------------------------------------------------------------------
    */

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
    ],

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
        'model' => env('MISTRAL_MODEL', 'mistral-large-latest'),
    ],

    'ollama' => [
        'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'llama2'),
        'enabled' => env('USE_OLLAMA_LLM', false),
        'embeddings_enabled' => env('USE_OLLAMA_EMBEDDINGS', true),
        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
    ],

    'huggingface' => [
        'api_key' => env('HUGGINGFACE_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM General Settings
    |--------------------------------------------------------------------------
    */

    'llm' => [
        'max_tokens' => (int) env('LLM_MAX_TOKENS', 4096),
        'temperature' => (float) env('LLM_TEMPERATURE', 0.3),
        'timeout' => (int) env('LLM_TIMEOUT', 60),
        'personality' => env('CHATBOT_PERSONALITY', 'professional'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Service Configuration
    |--------------------------------------------------------------------------
    */

    'embedding' => [
        'provider' => env('EMBEDDING_PROVIDER', 'openai'),
        'model' => env('EMBEDDING_MODEL', 'text-embedding-3-small'),
    ],

    'voyage' => [
        'api_key' => env('VOYAGE_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chatbot Configuration
    |--------------------------------------------------------------------------
    */

    'chatbot' => [
        'provider_order' => env('LLM_PROVIDER_ORDER', 'huggingface,openai,claude,ollama'),
        'default_personality' => env('CHATBOT_DEFAULT_PERSONALITY', 'professional'),
        'max_context_messages' => env('CHATBOT_MAX_CONTEXT_MESSAGES', 50),
        'enable_streaming' => env('CHATBOT_ENABLE_STREAMING', true),
        'enable_rag' => env('CHATBOT_ENABLE_RAG', true),
        'enable_memory' => env('CHATBOT_ENABLE_MEMORY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Services
    |--------------------------------------------------------------------------
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

];
