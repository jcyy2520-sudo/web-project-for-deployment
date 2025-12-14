<?php

header('Content-Type: application/json');

try {
    require 'vendor/autoload.php';
    $app = require_once 'bootstrap/app.php';
    $kernel = $app->make('Illuminate\Contracts\Http\Kernel');
    
    // Create a mock HTTP request
    $request = \Illuminate\Http\Request::create('/api/chatbot/send-message', 'POST', [], [], [], [], json_encode(['message' => 'Test']));
    $request->headers->set('Content-Type', 'application/json');
    
    // Handle the request
    $response = $kernel->handle($request);
    
    // Output the response
    echo $response->getContent();
    
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10)
    ], JSON_PRETTY_PRINT);
}
