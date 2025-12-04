@echo off
REM Chatbot Setup Script for Windows
REM This script sets up the chatbot database and dependencies

echo.
echo ========================================
echo  Chatbot AI Setup Script
echo ========================================
echo.

cd /d C:\laragon\www\web\web-backend

echo [1/3] Running database migrations...
php artisan migrate

if errorlevel 1 (
    echo.
    echo ERROR: Migration failed!
    echo Please ensure:
    echo - PHP is installed and accessible
    echo - Database connection is configured in .env
    echo - Laravel is properly set up
    pause
    exit /b 1
)

echo [2/3] Clearing application cache...
php artisan cache:clear
php artisan config:cache

echo [3/3] Setup complete!
echo.
echo ========================================
echo  Setup Completed Successfully!
echo ========================================
echo.
echo Next steps:
echo 1. Start your Laravel backend: php artisan serve
echo 2. In another terminal, start frontend: npm run dev
echo 3. Login to your application
echo 4. Look for the floating chat button in the bottom-right corner
echo.
echo For more information, see: CHATBOT_IMPLEMENTATION_GUIDE.md
echo.
pause
