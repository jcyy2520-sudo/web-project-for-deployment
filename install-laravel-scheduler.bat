@echo off
REM Laravel Scheduler - Windows Task Scheduler Setup
REM Run this as Administrator to register a scheduled task that runs every minute

setlocal enabledelayedexpansion

echo.
echo ========================================
echo Laravel Scheduler - Task Scheduler Setup
echo ========================================
echo.

REM Check if running as admin
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: This script must be run as Administrator
    echo Please right-click and select "Run as administrator"
    pause
    exit /b 1
)

set PHP_EXE=C:\laragon\bin\php\php8.3.14\php.exe
set BACKEND_DIR=C:\laragon\www\web\web-backend
set TASK_NAME=LaravelScheduler

echo PHP: %PHP_EXE%
echo Backend: %BACKEND_DIR%
echo Task Name: %TASK_NAME%
echo.

REM Verify php.exe exists
if not exist "%PHP_EXE%" (
    echo ERROR: php.exe not found at %PHP_EXE%
    echo Please update the PHP_EXE path in this script to match your Laragon PHP version.
    pause
    exit /b 1
)

REM Verify artisan exists
if not exist "%BACKEND_DIR%\artisan" (
    echo ERROR: artisan not found at %BACKEND_DIR%\artisan
    pause
    exit /b 1
)

REM Remove existing task if it exists
schtasks /delete /tn "%TASK_NAME%" /f >nul 2>&1

REM Create the scheduled task — runs every 1 minute, triggers on system startup, runs indefinitely
schtasks /create ^
    /tn "%TASK_NAME%" ^
    /tr "\"%PHP_EXE%\" \"%BACKEND_DIR%\artisan\" schedule:run >> \"%BACKEND_DIR%\storage\logs\scheduler.log\" 2>&1" ^
    /sc MINUTE ^
    /mo 1 ^
    /ru SYSTEM ^
    /f

if %errorLevel% equ 0 (
    echo.
    echo SUCCESS: Laravel Scheduler task registered.
    echo It will now run "php artisan schedule:run" every minute.
    echo This means appointments will be auto-archived on schedule.
    echo.
    echo Logs: %BACKEND_DIR%\storage\logs\scheduler.log
) else (
    echo.
    echo ERROR: Failed to create scheduled task. Check permissions.
)

pause
