<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

// Test the ChatbotController directly
try {
    $request = new \Illuminate\Http\Request();
    $request->setMethod('POST');
    $request->initialize([], [], [], [], [], ['REQUEST_METHOD' => 'POST'], json_encode(['message' => 'Test message', 'conversation_id' => 'test_123']));
    $request->headers->set('Content-Type', 'application/json');
    
    $controller = $app->make('App\Http\Controllers\ChatbotController');
    
    echo "Controller instantiated successfully!\n";
    echo "Methods available:\n";
    $reflection = new \ReflectionClass($controller);
    foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->getName(), '__')) {
            echo "  - " . $method->getName() . "()\n";
        }
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
