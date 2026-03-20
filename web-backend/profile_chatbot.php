<?php

use App\Services\UnifiedChatbotService;
use Illuminate\Support\Facades\Log;

// Simulating a Laravel environment for a standalone script
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

// We need a kernel to bootstrap the application
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(UnifiedChatbotService::class);

echo "--- Chatbot Performance Profile ---\n";

function profile($service, $message) {
    $userId = 1;
    $conversationId = "perf_test_" . time();
    
    $startTime = microtime(true);
    try {
        $result = $service->processMessage($message, $userId, $conversationId);
        $endTime = microtime(true);
        $time = round(($endTime - $startTime) * 1000, 2);
        
        $provider = $result['meta']['provider'] ?? 'unknown';
        $model = $result['meta']['model'] ?? 'unknown';
        
        echo "Query: \"$message\"\n";
        echo "Time: {$time}ms\n";
        echo "Layer: " . ($result['source'] ?? 'unknown') . "\n";
        echo "Provider: $provider ($model)\n";
        echo "Response: " . substr(strip_tags($result['response']), 0, 100) . "...\n";
        echo "---------------------------\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

profile($service, "Hello!");
profile($service, "What legal services do you provide?");
profile($service, "Book an appointment for tomorrow");
