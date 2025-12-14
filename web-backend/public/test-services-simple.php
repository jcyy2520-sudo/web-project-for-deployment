<?php

header('Content-Type: application/json');

try {
    require 'vendor/autoload.php';
    $app = require_once 'bootstrap/app.php';
    
    // Try to instantiate the role service
    $roleService = $app->make('App\Services\ChatbotRoleAwarenessService');
    
    echo json_encode([
        'success' => true,
        'message' => 'Services instantiated successfully',
        'service_classes' => [
            'roleService' => get_class($roleService)
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'previous' => $e->getPrevious() ? [
            'message' => $e->getPrevious()->getMessage(),
            'file' => $e->getPrevious()->getFile(),
            'line' => $e->getPrevious()->getLine()
        ] : null
    ], JSON_PRETTY_PRINT);
}
