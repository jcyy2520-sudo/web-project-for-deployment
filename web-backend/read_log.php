<?php
$logFile = 'c:/laragon/www/web/web-backend/storage/logs/laravel.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -100);
    echo implode("", $lastLines);
} else {
    echo "Log file not found.";
}
