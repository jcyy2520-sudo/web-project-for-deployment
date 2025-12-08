<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Check if refunds table exists
    $table_exists = \Illuminate\Support\Facades\Schema::hasTable('refunds');
    echo "Refunds table exists: " . ($table_exists ? 'YES' : 'NO') . PHP_EOL;
    
    if ($table_exists) {
        $count = \App\Models\Refund::count();
        echo "Refunds count: " . $count . PHP_EOL;
        
        // Check model
        echo "Refund model loads: YES" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
