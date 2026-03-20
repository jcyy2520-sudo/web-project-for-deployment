<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = User::orderBy('created_at', 'desc')->limit(10)->get();
foreach ($users as $user) {
    echo "ID: {$user->id}, Email: {$user->email}, GoogleID: {$user->google_id}, Created: {$user->created_at}\n";
}
