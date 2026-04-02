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

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
        'model' => env('MISTRAL_MODEL', 'mistral-large-latest'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-pro-latest'),
    ],

    'ollama' => [
        'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'llama2'),
        'enabled' => env('USE_OLLAMA_LLM', false),
        'embeddings_enabled' => env('USE_OLLAMA_EMBEDDINGS', true),
        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'api_url' => env('GROQ_ENDPOINT', 'https://api.groq.com/openai/v1'),
    ],

    'github_gpt5' => [
        'api_key' => env('GITHUB_TOKEN'),
        'model' => env('GITHUB_GPT5_MODEL', 'openai/gpt-5'),
        'api_url' => env('GITHUB_ENDPOINT', 'https://models.github.ai/inference'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'openai/gpt-5'),
        'api_url' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1'),
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
        'provider' => env('EMBEDDING_PROVIDER', 'voyage'),
        'model' => env('EMBEDDING_MODEL', 'voyage-2'),
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
        'provider_order' => ['github_gpt5', 'gemini', 'openai', 'mistral', 'groq', 'ollama'],
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
