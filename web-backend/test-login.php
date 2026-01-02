<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$userCount = User::count();
echo "Total users: " . $userCount . "\n";

$users = User::limit(5)->get();
echo "First 5 users:\n";
foreach ($users as $user) {
    echo "- {$user->email}\n";
}
