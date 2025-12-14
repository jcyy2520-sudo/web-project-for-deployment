<?php
/**
 * Advanced Chatbot Test Script
 * 
 * Run this script to test the new chatbot services:
 * php test-advanced-chatbot.php
 * 
 * Tests:
 * 1. LLM Provider connectivity
 * 2. Embedding service
 * 3. Memory service
 * 4. Smart suggestions
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Log;

echo "=== Advanced Chatbot Service Tests ===\n\n";

// Test 1: Check LLM Providers
echo "1. Testing LLM Providers...\n";
echo str_repeat("-", 40) . "\n";

try {
    $llmService = app(\App\Services\AdvancedLLMService::class);
    
    // Check which providers are configured
    $providers = [
        'Claude (Anthropic)' => config('services.anthropic.api_key'),
        'OpenAI' => config('services.openai.api_key'),
        'Mistral' => config('services.mistral.api_key'),
        'Ollama' => config('services.ollama.host'),
    ];
    
    foreach ($providers as $name => $key) {
        $status = !empty($key) ? '✓ Configured' : '✗ Not configured';
        echo "   {$name}: {$status}\n";
    }
    
    // Try a simple generation
    echo "\n   Attempting test generation...\n";
    $testContext = [
        'messages' => [
            ['role' => 'user', 'content' => 'Say hello in one word.']
        ]
    ];
    
    $response = $llmService->generateResponse('Say hello', $testContext);
    if ($response) {
        echo "   ✓ LLM Response received: " . substr($response, 0, 50) . "...\n";
    } else {
        echo "   ✗ No response received (no providers configured)\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Check Embedding Service
echo "2. Testing Embedding Service...\n";
echo str_repeat("-", 40) . "\n";

try {
    $embeddingService = app(\App\Services\EmbeddingService::class);
    
    // Check provider configuration
    $embeddingProvider = config('services.embedding.provider', 'openai');
    echo "   Provider: {$embeddingProvider}\n";
    
    // Test embedding generation
    $testEmbedding = $embeddingService->generateEmbedding('test message');
    if ($testEmbedding && count($testEmbedding) > 0) {
        echo "   ✓ Embedding generated: " . count($testEmbedding) . " dimensions\n";
    } else {
        echo "   ⚠ Embedding not available (provider not configured)\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Check Memory Service
echo "3. Testing Memory Service...\n";
echo str_repeat("-", 40) . "\n";

try {
    $memoryService = app(\App\Services\ChatbotMemoryService::class);
    
    // Test context retrieval
    $testUserId = 'test-user-' . time();
    $context = $memoryService->getConversationContext($testUserId);
    
    echo "   ✓ Memory service initialized\n";
    echo "   Max context messages: " . config('services.chatbot.max_context_messages', 50) . "\n";
    echo "   Memory enabled: " . (config('services.chatbot.enable_memory', true) ? 'Yes' : 'No') . "\n";
    
    // Test context update
    $memoryService->updateContext($testUserId, 'Test message', 'Test response');
    echo "   ✓ Context update working\n";
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Check Smart Suggestions
echo "4. Testing Smart Suggestion Service...\n";
echo str_repeat("-", 40) . "\n";

try {
    $suggestionService = app(\App\Services\SmartActionSuggestionService::class);
    
    // Test getting suggestions
    $testContext = [
        'intent' => 'booking',
        'user_id' => 'test-user',
        'conversation_history' => []
    ];
    
    $suggestions = $suggestionService->getSuggestions($testContext);
    echo "   ✓ Suggestion service initialized\n";
    echo "   Suggestions for 'booking' intent: " . count($suggestions) . "\n";
    
    // Test proactive suggestions
    $proactive = $suggestionService->getProactiveSuggestions($testContext);
    echo "   Proactive suggestions: " . count($proactive) . "\n";
    
    // Test quick actions
    $quickActions = $suggestionService->getQuickActions(null);
    echo "   Quick actions available: " . count($quickActions) . "\n";
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Check Database Tables
echo "5. Testing Database Tables...\n";
echo str_repeat("-", 40) . "\n";

try {
    $tables = [
        'chatbot_knowledge_base' => 'Knowledge base for RAG',
        'chatbot_conversation_embeddings' => 'Conversation embeddings',
        'user_preferences' => 'User preference learning',
    ];
    
    foreach ($tables as $table => $description) {
        $exists = \Illuminate\Support\Facades\Schema::hasTable($table);
        $status = $exists ? '✓ Exists' : '✗ Missing (run: php artisan migrate)';
        echo "   {$table}: {$status}\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 6: Check Configuration
echo "6. Configuration Summary...\n";
echo str_repeat("-", 40) . "\n";

$configs = [
    'Default Personality' => config('services.chatbot.default_personality', 'professional'),
    'Streaming Enabled' => config('services.chatbot.enable_streaming', true) ? 'Yes' : 'No',
    'RAG Enabled' => config('services.chatbot.enable_rag', true) ? 'Yes' : 'No',
    'Memory Enabled' => config('services.chatbot.enable_memory', true) ? 'Yes' : 'No',
    'Broadcast Driver' => config('broadcasting.default', 'log'),
];

foreach ($configs as $name => $value) {
    echo "   {$name}: {$value}\n";
}

echo "\n";
echo "=== Test Complete ===\n";
echo "\nNext Steps:\n";
echo "1. Configure at least one LLM provider in .env\n";
echo "2. Run: php artisan migrate (if tables are missing)\n";
echo "3. Test the API endpoint: GET /api/chatbot/status\n";
echo "4. Try streaming: POST /api/chatbot/stream\n";
