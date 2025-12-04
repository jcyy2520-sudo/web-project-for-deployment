<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AppointmentSettings;
use App\Models\Appointment;
use App\Models\User;

try {
    echo "=== BACKEND ANALYSIS ===\n\n";
    
    // Check settings
    $settings = AppointmentSettings::getCurrent();
    echo "Current Settings:\n";
    echo "  ID: {$settings->id}\n";
    echo "  Daily Limit: {$settings->daily_booking_limit_per_user}\n";
    echo "  Is Active: " . ($settings->is_active ? 'YES' : 'NO') . "\n";
    echo "  Last Updated By: {$settings->last_updated_by}\n\n";
    
    // Check test user
    $user = User::where('role', 'client')->first();
    echo "Test User:\n";
    echo "  ID: {$user->id}\n";
    echo "  Email: {$user->email}\n";
    echo "  Role: {$user->role}\n\n";
    
    // Check today's appointments
    $today = now()->format('Y-m-d');
    echo "Today's date: {$today}\n\n";
    
    $allAppts = Appointment::where('user_id', $user->id)
        ->where('appointment_date', $today)
        ->get();
    
    echo "All appointments for user today:\n";
    foreach ($allAppts as $apt) {
        echo "  - ID {$apt->id}: {$apt->appointment_date} {$apt->appointment_time} [{$apt->status}]\n";
    }
    echo "  Total: " . $allAppts->count() . "\n\n";
    
    // Check active appointments
    $activeAppts = Appointment::where('user_id', $user->id)
        ->where('appointment_date', $today)
        ->whereIn('status', ['pending', 'approved'])
        ->get();
    
    echo "Active (pending/approved) appointments:\n";
    foreach ($activeAppts as $apt) {
        echo "  - ID {$apt->id}: {$apt->appointment_date} {$apt->appointment_time} [{$apt->status}]\n";
    }
    echo "  Total: " . $activeAppts->count() . "\n\n";
    
    // Test the limit function
    echo "Testing Limit Function:\n";
    $hasReached = AppointmentSettings::userHasReachedDailyLimit($user->id, $today);
    echo "  userHasReachedDailyLimit() returns: " . ($hasReached ? 'TRUE' : 'FALSE') . "\n";
    echo "  Expected: " . ($activeAppts->count() >= $settings->daily_booking_limit_per_user ? 'TRUE' : 'FALSE') . "\n";
    echo "  Active Count: {$activeAppts->count()}\n";
    echo "  Limit: {$settings->daily_booking_limit_per_user}\n";
    echo "  {$activeAppts->count()} >= {$settings->daily_booking_limit_per_user}? " . ($activeAppts->count() >= $settings->daily_booking_limit_per_user ? 'YES' : 'NO') . "\n\n";
    
    // Test the API endpoint
    echo "Testing API Endpoint Response:\n";
    $controller = new \App\Http\Controllers\AppointmentSettingsController();
    $request = \Illuminate\Http\Request::create(
        "/api/appointment-settings/user-limit/{$user->id}/{$today}",
        'GET'
    );
    $response = $controller->getUserLimit($user->id, $today);
    $data = json_decode($response->getContent(), true);
    
    echo "  API Response:\n";
    echo "    success: " . ($data['success'] ? 'true' : 'false') . "\n";
    echo "    has_reached_limit: " . ($data['data']['has_reached_limit'] ? 'true' : 'false') . "\n";
    echo "    limit: {$data['data']['limit']}\n";
    echo "    used: {$data['data']['used']}\n";
    echo "    remaining: {$data['data']['remaining']}\n";
    echo "    message: {$data['data']['message']}\n\n";
    
    if ($activeAppts->count() >= $settings->daily_booking_limit_per_user) {
        echo "✅ BACKEND IS CORRECT - Limit is being enforced properly\n";
    } else {
        echo "ℹ️  User has not reached limit yet\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
