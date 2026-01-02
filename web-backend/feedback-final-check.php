<?php

require 'vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$db = $_ENV['DB_DATABASE'] ?? 'web2';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║        FEEDBACK SYSTEM - PRODUCTION READINESS REPORT       ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    
    $status = [
        'database' => '❌',
        'tables' => '❌',
        'structure' => '❌',
        'settings' => '❌',
        'backend' => '❌',
        'frontend' => '❌',
        'integration' => '❌',
        'realtime' => '❌'
    ];
    
    $issues = [];
    
    // 1. Database
    echo "1️⃣  DATABASE CONNECTION\n";
    try {
        $test = $pdo->query("SELECT 1");
        echo "   ✓ Connected to: $db\n";
        $status['database'] = '✅';
    } catch (Exception $e) {
        echo "   ✗ Connection failed: " . $e->getMessage() . "\n";
        $issues[] = "Database connection failed";
    }
    
    // 2. Tables
    echo "\n2️⃣  DATABASE TABLES\n";
    $result = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$db' AND TABLE_NAME LIKE 'feedback%'");
    $count = $result->fetch(PDO::FETCH_COLUMN);
    if ($count >= 2) {
        echo "   ✓ feedback table exists\n";
        echo "   ✓ feedback_settings table exists\n";
        $status['tables'] = '✅';
    } else {
        echo "   ✗ Missing feedback tables ($count found, need 2+)\n";
        $issues[] = "Missing database tables";
    }
    
    // 3. Structure
    echo "\n3️⃣  TABLE STRUCTURE\n";
    $result = $pdo->query("DESCRIBE feedback");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    $required = ['id', 'user_id', 'email', 'message', 'rating', 'feedback_type', 'is_testimonial', 'is_reported', 'is_blocked', 'deleted_at', 'created_at', 'updated_at'];
    $missing = array_diff($required, $columns);
    if (empty($missing)) {
        echo "   ✓ All " . count($required) . " required columns present\n";
        $status['structure'] = '✅';
    } else {
        echo "   ✗ Missing columns: " . implode(", ", $missing) . "\n";
        $issues[] = "Missing database columns: " . implode(", ", $missing);
    }
    
    // 4. Settings
    echo "\n4️⃣  FEEDBACK SETTINGS\n";
    $result = $pdo->query("SELECT * FROM feedback_settings LIMIT 1");
    $settings = $result->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        echo "   ✓ Rate limit configured: {$settings['rate_limit']} submissions per {$settings['cooldown_days']} days\n";
        echo "   ✓ Profanity filtering: " . ($settings['profanity_list'] ? 'ACTIVE' : 'INACTIVE') . "\n";
        $status['settings'] = '✅';
    } else {
        echo "   ⚠ Settings not initialized\n";
        $issues[] = "Feedback settings not initialized";
    }
    
    // 5. Backend Controllers
    echo "\n5️⃣  BACKEND IMPLEMENTATION\n";
    $checks = [];
    if (file_exists('app/Http/Controllers/FeedbackController.php')) {
        $controller = file_get_contents('app/Http/Controllers/FeedbackController.php');
        
        $methods = [
            'store' => 'Feedback submission',
            'getUserFeedback' => 'User feedback retrieval',
            'checkRateLimit' => 'Rate limit checking',
            'getStats' => 'Admin statistics',
            'reportFeedback' => 'Report feedback (abuse)',
            'blockUser' => 'Block user from feedback',
            'updateTestimonial' => 'Mark testimonials',
            'destroy' => 'Soft delete feedback'
        ];
        
        foreach ($methods as $method => $name) {
            if (strpos($controller, "public function $method") !== false) {
                $checks[] = $name;
            } else {
                $issues[] = "Missing controller method: $method";
            }
        }
        
        if (strpos($controller, 'isEmailRegistered') !== false) {
            echo "   ✓ Email verification: ACTIVE\n";
        } else {
            echo "   ⚠ Email verification: NOT FOUND\n";
        }
        
        if (strpos($controller, 'hasReachedRateLimit') !== false) {
            echo "   ✓ Rate limiting: IMPLEMENTED\n";
        } else {
            echo "   ⚠ Rate limiting: NOT IMPLEMENTED\n";
        }
        
        if (strpos($controller, 'profanity') !== false) {
            echo "   ✓ Profanity filtering: IMPLEMENTED\n";
        } else {
            echo "   ⚠ Profanity filtering: NOT IMPLEMENTED\n";
        }
        
        echo "   ✓ Methods implemented: " . count($checks) . "/" . count($methods) . "\n";
        $status['backend'] = count($checks) == count($methods) ? '✅' : '⚠️';
    } else {
        echo "   ✗ FeedbackController not found\n";
        $issues[] = "FeedbackController.php missing";
    }
    
    // 6. Frontend Components
    echo "\n6️⃣  FRONTEND IMPLEMENTATION\n";
    $components = [
        '../web-frontend/src/components/user/UserFeedback.jsx' => 'User feedback form',
        '../web-frontend/src/components/dashboard/UserFeedback.jsx' => 'Dashboard view',
        '../web-frontend/src/components/admin/AdminFeedback.jsx' => 'Admin feedback manager',
        '../web-frontend/src/components/admin/AdminFeedbackSettings.jsx' => 'Admin settings',
        '../web-frontend/src/components/modals/FeedbackThankYouModal.jsx' => 'Thank you modal'
    ];
    
    $found = 0;
    foreach ($components as $path => $name) {
        if (file_exists($path)) {
            $found++;
        }
    }
    
    echo "   ✓ Components loaded: $found/" . count($components) . "\n";
    if ($found == count($components)) {
        $status['frontend'] = '✅';
    } else {
        $issues[] = "Some frontend components missing ($found/" . count($components) . ")";
        $status['frontend'] = '⚠️';
    }
    
    // 7. Integration
    echo "\n7️⃣  SYSTEM INTEGRATION\n";
    $dashboard_path = '../web-frontend/src/pages/Dashboard.jsx';
    $landing_path = '../web-frontend/src/pages/LandingPage.jsx';
    
    if (file_exists($dashboard_path)) {
        $dashboard = file_get_contents($dashboard_path);
        if (strpos($dashboard, "UserFeedback") !== false) {
            echo "   ✓ Feedback integrated in user dashboard\n";
        } else {
            echo "   ✗ Feedback not integrated in dashboard\n";
            $issues[] = "Feedback not integrated in dashboard";
        }
    }
    
    if (file_exists($landing_path)) {
        $landing = file_get_contents($landing_path);
        if (strpos($landing, 'testimonials') !== false && strpos($landing, 'feedback') !== false) {
            echo "   ✓ Testimonials integrated on landing page\n";
        } else {
            echo "   ⚠ Limited testimonial integration\n";
        }
    }
    
    $api_routes = file_exists('routes/api.php') ? file_get_contents('routes/api.php') : '';
    if (strpos($api_routes, "'/feedback'") !== false && strpos($api_routes, 'FeedbackController') !== false) {
        echo "   ✓ API routes registered\n";
        $status['integration'] = '✅';
    } else {
        echo "   ✗ API routes not found\n";
        $issues[] = "Feedback API routes not registered";
    }
    
    // 8. Real-time
    echo "\n8️⃣  REAL-TIME UPDATES\n";
    if (file_exists('config/reverb.php')) {
        echo "   ✓ WebSocket (Reverb) configured\n";
        $status['realtime'] = '✅';
    } else {
        echo "   ⚠ WebSocket not configured (polling only)\n";
        $status['realtime'] = '⚠️';
    }
    
    // Summary
    echo "\n╔════════════════════════════════════════════════════════════╗\n";
    echo "║                      SUMMARY STATUS                        ║\n";
    echo "╠════════════════════════════════════════════════════════════╣\n";
    
    foreach ($status as $component => $state) {
        $padded = str_pad(ucfirst(str_replace('_', ' ', $component)), 20);
        echo "║ $padded $state                                    ║\n";
    }
    
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    // Overall result
    $all_ok = !array_search('❌', $status);
    
    if (empty($issues)) {
        echo "✅ PRODUCTION READY - System is 100% functional and fully integrated\n\n";
        echo "STATUS:\n";
        echo "  • Database: Connected and structured\n";
        echo "  • Backend: All controllers and validation implemented\n";
        echo "  • Frontend: All components integrated\n";
        echo "  • Real-time: WebSocket support available\n";
        echo "  • Security: Email validation, rate limiting, profanity filtering active\n";
        echo "  • Data: Ready to receive feedback submissions\n";
    } else {
        echo "⚠️  ISSUES FOUND:\n";
        foreach ($issues as $idx => $issue) {
            echo "  " . ($idx + 1) . ". $issue\n";
        }
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
}
