# Chatbot AI Setup Script for PowerShell
# This script sets up the chatbot database and dependencies

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Chatbot AI Setup Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Navigate to backend directory
Set-Location "C:\laragon\www\web\web-backend"

# Check if we're in the right directory
if (-not (Test-Path "artisan")) {
    Write-Host "ERROR: artisan file not found!" -ForegroundColor Red
    Write-Host "Please run this script from the project root or ensure you have the correct path."
    Read-Host "Press Enter to exit"
    exit 1
}

# Step 1: Run migrations
Write-Host "[1/3] Running database migrations..." -ForegroundColor Yellow
php artisan migrate

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "ERROR: Migration failed!" -ForegroundColor Red
    Write-Host "Please ensure:" -ForegroundColor Red
    Write-Host "- PHP is installed and accessible" -ForegroundColor Red
    Write-Host "- Database connection is configured in .env" -ForegroundColor Red
    Write-Host "- Laravel is properly set up" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

# Step 2: Clear cache
Write-Host "[2/3] Clearing application cache..." -ForegroundColor Yellow
php artisan cache:clear
php artisan config:cache

# Step 3: Done
Write-Host "[3/3] Setup complete!" -ForegroundColor Green
Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host " Setup Completed Successfully!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "1. Start your Laravel backend: php artisan serve" -ForegroundColor White
Write-Host "2. In another terminal, start frontend: npm run dev" -ForegroundColor White
Write-Host "3. Login to your application" -ForegroundColor White
Write-Host "4. Look for the floating chat button in the bottom-right corner" -ForegroundColor White
Write-Host ""
Write-Host "For more information, see: CHATBOT_IMPLEMENTATION_GUIDE.md" -ForegroundColor White
Write-Host ""
Read-Host "Press Enter to close this window"
