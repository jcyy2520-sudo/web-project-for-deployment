<?php
/**
 * Cache clearing utility for cPanel deployment
 * DELETE THIS FILE AFTER USE for security
 * 
 * Access via: https://legaleaase.site/clear-cache.php?key=notary2024clear
 */

// SECURITY: Block access in production unless explicitly enabled
if (getenv('APP_ENV') === 'production' && !getenv('ALLOW_CACHE_CLEAR')) {
    http_response_code(404);
    die('Not Found');
}

// Simple security key to prevent unauthorized access
$securityKey = getenv('CACHE_CLEAR_KEY') ?: 'notary2024clear';

if (!isset($_GET['key']) || $_GET['key'] !== $securityKey) {
    http_response_code(403);
    die('Access denied. Please provide the correct security key.');
}

// Change to the Laravel root directory
chdir(__DIR__ . '/..');

// Clear various caches
$commands = [
    'config:clear' => 'php artisan config:clear',
    'cache:clear' => 'php artisan cache:clear',
    'route:clear' => 'php artisan route:clear',
    'view:clear' => 'php artisan view:clear',
];

echo "<pre>";
echo "=== Laravel Cache Clearing Utility ===\n\n";

foreach ($commands as $name => $command) {
    echo "Running: $name\n";
    $output = shell_exec($command . ' 2>&1');
    echo $output . "\n";
}

echo "\n=== All caches cleared! ===\n";
echo "\n⚠️ IMPORTANT: Delete this file (clear-cache.php) after use for security!\n";
echo "</pre>";
