<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test service creation
try {
    echo "Testing service creation...\n";
    $service = \App\Models\Service::create([
        'name' => 'Test Service ' . time(),
        'description' => 'Test description',
        'price' => 100,
        'duration' => 30,
        'is_active' => true
    ]);
    echo "Created service: " . $service->id . " - " . $service->name . "\n";
    
    // Clean up
    $service->forceDelete();
    echo "Service deleted successfully\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

// Check user stats
echo "\n--- User Statistics ---\n";
echo "Total users (with trashed): " . \App\Models\User::withTrashed()->count() . "\n";
echo "Active users: " . \App\Models\User::count() . "\n";
echo "Trashed users: " . \App\Models\User::onlyTrashed()->count() . "\n";
echo "Clients: " . \App\Models\User::where('role', 'client')->count() . "\n";
echo "Admins: " . \App\Models\User::where('role', 'admin')->count() . "\n";
echo "Staff: " . \App\Models\User::where('role', 'staff')->count() . "\n";

// List first 5 trashed users
$trashedUsers = \App\Models\User::onlyTrashed()->take(5)->get(['id', 'email', 'role', 'deleted_at']);
echo "\nFirst 5 trashed users:\n";
foreach ($trashedUsers as $user) {
    echo "  - ID: {$user->id}, Email: {$user->email}, Role: {$user->role}, Deleted: {$user->deleted_at}\n";
}
