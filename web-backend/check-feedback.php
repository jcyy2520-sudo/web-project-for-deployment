<?php

require 'vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$db = $_ENV['DB_DATABASE'] ?? 'web2';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    echo "✓ Database connected (web2)\n";
    
    // Check feedback tables
    $result = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$db' AND TABLE_NAME LIKE 'feedback%'");
    $tables = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== FEEDBACK TABLES ===\n";
    if (empty($tables)) {
        echo "✗ NO FEEDBACK TABLES FOUND!\n";
    } else {
        foreach ($tables as $table) {
            echo "✓ " . $table['TABLE_NAME'] . "\n";
        }
    }
    
    // Check feedback record count
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM feedback");
    $count = $result->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "\n=== FEEDBACK RECORDS ===\n";
    echo "Total: $count\n";
    
    // Check settings
    $result = $pdo->query("SELECT * FROM feedback_settings LIMIT 1");
    $settings = $result->fetch(PDO::FETCH_ASSOC);
    echo "\n=== FEEDBACK SETTINGS ===\n";
    if ($settings) {
        echo "Rate limit: {$settings['rate_limit']}\n";
        echo "Cooldown days: {$settings['cooldown_days']}\n";
    } else {
        echo "✗ No settings found\n";
    }
    
    // Testimonials count
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM feedback WHERE is_testimonial=1 AND deleted_at IS NULL");
    $testimonials = $result->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "\n=== TESTIMONIALS ===\n";
    echo "Active testimonials: $testimonials\n";
    
    // Recent feedback
    $result = $pdo->query("SELECT id, message, rating, deleted_at FROM feedback ORDER BY created_at DESC LIMIT 3");
    $recent = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "\n=== RECENT FEEDBACK (Last 3) ===\n";
    if (empty($recent)) {
        echo "No feedback found\n";
    } else {
        foreach ($recent as $f) {
            $status = $f['deleted_at'] ? "DELETED" : "ACTIVE";
            echo "ID {$f['id']}: {$f['message']} (Rating: {$f['rating']}) - $status\n";
        }
    }
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
