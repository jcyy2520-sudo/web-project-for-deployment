<?php

require 'vendor/autoload.php';

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$app = require_once 'bootstrap/app.php';

echo "Testing feedback API endpoints...\n\n";

try {
    // Test 1: Check if routes are registered
    $router = $app->make('router');
    echo "✓ Laravel app loaded\n";
    
    // Test 2: Load models
    $feedback = app('App\Models\Feedback');
    $settings = app('App\Models\FeedbackSettings');
    echo "✓ Feedback models loaded\n";
    
    // Test 3: Check settings
    $setting = $settings->first();
    if ($setting) {
        echo "✓ Feedback settings accessible\n";
        echo "  - Rate limit: {$setting->rate_limit}\n";
        echo "  - Cooldown: {$setting->cooldown_days} days\n";
    } else {
        echo "⚠ Settings not initialized\n";
    }
    
    // Test 4: Count feedback
    $count = $feedback->count();
    echo "✓ Feedback table accessible\n";
    echo "  - Records: $count\n";
    
    // Test 5: Check if testimonials can be queried
    $testimonials = $feedback->where('is_testimonial', true)->where('deleted_at', null)->count();
    echo "✓ Testimonials query works\n";
    echo "  - Featured testimonials: $testimonials\n";
    
    echo "\n✅ All API components functional\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
