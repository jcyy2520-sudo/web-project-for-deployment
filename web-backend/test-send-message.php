<?php

require 'vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\ChatbotController;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

try {
    // Create a mock request
    $request = Request::create('/api/chatbot/send-message', 'POST', [], [], [], [], json_encode([
        'message' => 'How do I book an appointment?',
        'conversation_id' => 'test_001'
    ]));
    $request->headers->set('Content-Type', 'application/json');
    
    // Get controller instance
    $controller = $app->make(ChatbotController::class);
    
    echo "Testing sendMessage method...\n\n";
    
    $response = $controller->sendMessage($request);
    
    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Content:\n";
    echo $response->getContent() . "\n";
    
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Trace:\n";
    echo $e->getTraceAsString() . "\n";
}
