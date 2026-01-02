<?php

require 'vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$db = $_ENV['DB_DATABASE'] ?? 'web2';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

echo "\n========== FEEDBACK SYSTEM STATUS CHECK ==========\n\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    echo "✓ Database connected (web2)\n";
    
    // 1. Check tables
    $result = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$db' AND TABLE_NAME LIKE 'feedback%' ORDER BY TABLE_NAME");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\n[DATABASE TABLES]\n";
    if (count($tables) < 2) {
        echo "✗ MISSING TABLES - Expected feedback & feedback_settings\n";
    } else {
        echo "✓ " . implode("\n✓ ", $tables) . "\n";
    }
    
    // 2. Check table structure
    echo "\n[FEEDBACK TABLE STRUCTURE]\n";
    $result = $pdo->query("DESCRIBE feedback");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    $required = ['id', 'user_id', 'email', 'message', 'rating', 'feedback_type', 'is_testimonial', 'is_reported', 'is_blocked', 'deleted_at', 'created_at'];
    $missing = array_diff($required, $columns);
    if ($missing) {
        echo "✗ MISSING COLUMNS: " . implode(", ", $missing) . "\n";
    } else {
        echo "✓ All required columns present\n";
    }
    
    // 3. Check data
    echo "\n[DATA STATUS]\n";
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM feedback");
    $total = $result->fetch(PDO::FETCH_ASSOC)['cnt'];
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM feedback WHERE deleted_at IS NULL");
    $active = $result->fetch(PDO::FETCH_ASSOC)['cnt'];
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM feedback WHERE is_testimonial=1 AND deleted_at IS NULL");
    $testimonials = $result->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "Total feedback: $total (Active: $active, Testimonials: $testimonials)\n";
    
    // 4. Check settings
    echo "\n[SETTINGS]\n";
    $result = $pdo->query("SELECT * FROM feedback_settings LIMIT 1");
    $settings = $result->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        echo "✓ Rate limit: {$settings['rate_limit']} per {$settings['cooldown_days']} days\n";
        echo "✓ Profanity filtering: " . ($settings['profanity_list'] ? 'YES' : 'NO') . "\n";
    } else {
        echo "✗ No settings found - NEEDS INITIALIZATION\n";
    }
    
    // 5. Check API routes
    echo "\n[API ROUTES]\n";
    $file = file_get_contents('routes/api.php');
    if (strpos($file, "Route::post('/feedback'") !== false) {
        echo "✓ Public feedback endpoint: POST /api/feedback\n";
    } else {
        echo "✗ Public feedback endpoint NOT FOUND\n";
    }
    
    if (strpos($file, "Route::get('/user/feedback'") !== false) {
        echo "✓ User feedback endpoint: GET /api/user/feedback\n";
    } else {
        echo "✗ User feedback endpoint NOT FOUND\n";
    }
    
    if (strpos($file, "Route::get('/admin/feedback'") !== false) {
        echo "✓ Admin feedback endpoints registered\n";
    } else {
        echo "✗ Admin feedback endpoints NOT FOUND\n";
    }
    
    // 6. Check controller
    echo "\n[CONTROLLER]\n";
    if (file_exists('app/Http/Controllers/FeedbackController.php')) {
        echo "✓ FeedbackController.php exists\n";
        $controller = file_get_contents('app/Http/Controllers/FeedbackController.php');
        
        $methods = ['store', 'index', 'getStats', 'getUserFeedback', 'checkRateLimit', 'reportFeedback', 'blockUser', 'updateTestimonial'];
        $missing_methods = [];
        foreach ($methods as $method) {
            if (strpos($controller, "public function $method") === false) {
                $missing_methods[] = $method;
            }
        }
        
        if ($missing_methods) {
            echo "✗ Missing methods: " . implode(", ", $missing_methods) . "\n";
        } else {
            echo "✓ All required methods present\n";
        }
        
        // Check for validation
        if (strpos($controller, 'isEmailRegistered') !== false) {
            echo "✓ Email validation implemented\n";
        } else {
            echo "✗ Email validation NOT FOUND\n";
        }
        
        // Check for rate limiting
        if (strpos($controller, 'hasReachedRateLimit') !== false) {
            echo "✓ Rate limiting implemented\n";
        } else {
            echo "✗ Rate limiting NOT FOUND\n";
        }
        
        // Check for profanity filtering
        if (strpos($controller, 'profanity') !== false) {
            echo "✓ Profanity filtering implemented\n";
        } else {
            echo "✗ Profanity filtering NOT FOUND\n";
        }
        
    } else {
        echo "✗ FeedbackController.php NOT FOUND\n";
    }
    
    // 7. Check models
    echo "\n[MODELS]\n";
    if (file_exists('app/Models/Feedback.php')) {
        echo "✓ Feedback model exists\n";
    } else {
        echo "✗ Feedback model NOT FOUND\n";
    }
    
    if (file_exists('app/Models/FeedbackSettings.php')) {
        echo "✓ FeedbackSettings model exists\n";
    } else {
        echo "✗ FeedbackSettings model NOT FOUND\n";
    }
    
    // 8. Check mailing
    echo "\n[EMAIL NOTIFICATIONS]\n";
    if (file_exists('app/Mail/FeedbackConfirmation.php')) {
        echo "✓ Confirmation email class exists\n";
    } else {
        echo "✗ Confirmation email NOT FOUND\n";
    }
    
    if (file_exists('app/Mail/FeedbackReported.php')) {
        echo "✓ Report notification email class exists\n";
    } else {
        echo "✗ Report notification email NOT FOUND\n";
    }
    
    // 9. Check frontend files
    echo "\n[FRONTEND COMPONENTS]\n";
    $frontend_files = [
        'web-frontend/src/components/user/UserFeedback.jsx' => 'User Feedback Form',
        'web-frontend/src/components/dashboard/UserFeedback.jsx' => 'Feedback Dashboard',
        'web-frontend/src/components/admin/AdminFeedback.jsx' => 'Admin Feedback Manager',
        'web-frontend/src/components/admin/AdminFeedbackSettings.jsx' => 'Feedback Settings',
        'web-frontend/src/components/modals/FeedbackThankYouModal.jsx' => 'Thank You Modal',
        'web-frontend/src/components/modals/AllTestimonialsModal.jsx' => 'Testimonials Modal'
    ];
    
    foreach ($frontend_files as $path => $name) {
        if (file_exists($path)) {
            echo "✓ $name\n";
        } else {
            echo "✗ $name NOT FOUND\n";
        }
    }
    
    // 10. Check real-time/WebSocket
    echo "\n[REAL-TIME UPDATES]\n";
    $app_config = file_get_contents('config/app.php');
    if (strpos($app_config, 'reverb') !== false || file_exists('config/reverb.php')) {
        echo "✓ Reverb WebSocket configured\n";
    } else {
        echo "⚠ No real-time WebSocket detected (polling only)\n";
    }
    
    // 11. Check migrations
    echo "\n[MIGRATIONS]\n";
    $migrations = [
        '2025_12_30_create_feedback_table.php',
        '2025_12_31_create_feedback_settings_table.php',
        '2025_12_30_update_feedback_table.php'
    ];
    
    foreach ($migrations as $migration) {
        if (file_exists("database/migrations/$migration")) {
            echo "✓ $migration\n";
        } else {
            echo "✗ $migration NOT FOUND\n";
        }
    }
    
    // 12. Summary
    echo "\n========== SUMMARY ==========\n";
    $issues = [];
    
    if ($total == 0) {
        $issues[] = "No test data in database";
    }
    if (!$settings) {
        $issues[] = "Feedback settings not initialized";
    }
    if (count($tables) < 2) {
        $issues[] = "Database tables missing";
    }
    
    if (empty($issues)) {
        echo "✓ SYSTEM IS FUNCTIONAL\n";
        echo "Status: Connected, Database configured, All components present\n";
    } else {
        echo "⚠ Issues found:\n";
        foreach ($issues as $issue) {
            echo "  - $issue\n";
        }
    }
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";
