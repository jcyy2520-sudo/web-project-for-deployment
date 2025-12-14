<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

try {
    echo "Testing service instantiation...\n\n";
    
    // Test 1: Role Awareness Service
    echo "1. Testing ChatbotRoleAwarenessService instantiation...\n";
    try {
        $roleService = $app->make('App\Services\ChatbotRoleAwarenessService');
        echo "   ✓ Service instantiated\n";
        
        echo "2. Calling detectUserRole(null)...\n";
        try {
            $roleInfo = $roleService->detectUserRole(null);
            echo "   ✓ Role detected: " . $roleInfo['primary_role'] . "\n";
        } catch (\Throwable $e2) {
            echo "   ✗ detectUserRole failed: " . $e2->getMessage() . "\n";
            echo "   File: " . $e2->getFile() . ":" . $e2->getLine() . "\n";
        }
    } catch (\Throwable $e1) {
        echo "   ✗ Service instantiation failed: " . $e1->getMessage() . "\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

