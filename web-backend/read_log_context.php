<?php
$logFile = 'c:/laragon/www/web/web-backend/storage/logs/laravel.log';
$lines = file($logFile);
$foundIndex = -1;
foreach ($lines as $index => $line) {
    if (strpos($line, '2026-03-17 12:30:12') !== false) {
        $foundIndex = $index;
        break;
    }
}

if ($foundIndex !== -1) {
    for ($i = $foundIndex - 2; $i < $foundIndex + 20; $i++) {
        if (isset($lines[$i])) echo $lines[$i];
    }
}
