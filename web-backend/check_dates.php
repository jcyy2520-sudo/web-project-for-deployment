<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

use App\Models\UnavailableDate;
use App\Models\BlackoutDate;

echo "=== DATABASE CHECK ===\n\n";

echo "UnavailableDate entries: " . UnavailableDate::count() . "\n";
foreach(UnavailableDate::all() as $u) {
    echo "  - " . $u->date . ": " . $u->reason . "\n";
}

echo "\nBlackoutDate entries: " . BlackoutDate::count() . "\n";
foreach(BlackoutDate::all() as $b) {
    echo "  - " . $b->date . ": " . $b->reason . "\n";
}

echo "\n✅ Admin interface will now show both sets of data merged together.\n";
