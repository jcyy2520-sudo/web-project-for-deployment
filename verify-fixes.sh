#!/bin/bash
# Verification script for booking system fixes

echo "=== BOOKING SYSTEM VERIFICATION REPORT ==="
echo "Current Date: $(date)"
echo ""

echo "1. BACKEND API ROUTE CHECK"
echo "=================================="
cd /d c:\laragon\www\web\web-backend
php artisan route:list | grep -i "unavailable-dates" | head -20

echo ""
echo "2. BACKEND DATABASE CHECK"
echo "=================================="
php artisan tinker --execute "
  echo 'Blackout Dates in Database: ' . \App\Models\BlackoutDate::count() . PHP_EOL;
  echo 'Unavailable Dates in Database: ' . \App\Models\UnavailableDate::count() . PHP_EOL;
  echo PHP_EOL . 'Sample Data:' . PHP_EOL;
  \App\Models\BlackoutDate::limit(2)->get()->each(function(\$d) {
    echo '  [BlackoutDate] ' . \$d->date . ' - ' . \$d->reason . PHP_EOL;
  });
  \App\Models\UnavailableDate::limit(2)->get()->each(function(\$d) {
    echo '  [UnavailableDate] ' . \$d->date . ' - ' . \$d->reason . PHP_EOL;
  });
"

echo ""
echo "3. FRONTEND CODE VERIFICATION"
echo "=================================="
echo "a) loadUnavailableDates function - skipCache setting:"
grep -A 3 "loadUnavailableDates = async" /d c:\laragon\www\web\web-frontend\src\pages\ClientAppointments.jsx | grep -i "skipcache\|cache"

echo ""
echo "b) API Config - unavailable-dates in noCacheEndpoints:"
grep -A 10 "noCacheEndpoints" /d c:\laragon\www\web\web-frontend\src\config\apiConfig.js | grep -i "unavailable-dates"

echo ""
echo "4. ROUTE FILE VERIFICATION"
echo "=================================="
echo "a) Public /api/unavailable-dates route (outside auth):"
grep -B 2 "api/unavailable-dates" /d c:\laragon\www\web\web-backend\routes\api.php | head -20

echo ""
echo "5. BUILD STATUS"
echo "=================================="
echo "Frontend dist build time: $(stat /d c:\laragon\www\web\web-frontend\dist\index.html | grep Modify)"
echo "Latest component: $(ls -lt /d c:\laragon\www\web\web-frontend\dist/assets/*.js | head -1)"

echo ""
echo "=== VERIFICATION COMPLETE ==="
