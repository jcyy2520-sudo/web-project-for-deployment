<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$googleUsers = User::whereNotNull('google_id')->count();
$totalUsers = User::count();
echo "Total Users: $totalUsers, Google Users: $googleUsers\n";

$latestGoogleUser = User::whereNotNull('google_id')->orderBy('created_at', 'desc')->first();
if ($latestGoogleUser) {
    echo "Latest Google User: ID {$latestGoogleUser->id}, Email {$latestGoogleUser->email}, Created {$latestGoogleUser->created_at}\n";
} else {
    echo "No Google users found.\n";
}
