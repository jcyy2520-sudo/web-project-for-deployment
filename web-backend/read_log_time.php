<?php
$logFile = 'c:/laragon/www/web/web-backend/storage/logs/laravel.log';
$lines = file($logFile);
foreach ($lines as $line) {
    if (strpos($line, '2026-03-17 12:30:12') !== false || strpos($line, '12:30:13') !== false) {
        echo $line;
    }
}
