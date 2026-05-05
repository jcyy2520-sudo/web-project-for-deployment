@echo off
setlocal

rem Resolve project root from this script location
set "ROOT=%~dp0"
set "BACKEND_DIR=%ROOT%web-backend"
set "FRONTEND_DIR=%ROOT%web-frontend"
set "ML_SERVICE_SCRIPT=%ROOT%start-ml-service.bat"

if not exist "%BACKEND_DIR%\artisan" (
    echo [ERROR] Backend folder is not valid: "%BACKEND_DIR%"
    echo Expected file not found: artisan
    pause
    exit /b 1
)

if not exist "%FRONTEND_DIR%\package.json" (
    echo [ERROR] Frontend folder is not valid: "%FRONTEND_DIR%"
    echo Expected file not found: package.json
    pause
    exit /b 1
)

if not exist "%ML_SERVICE_SCRIPT%" (
    echo [ERROR] ML service launcher not found: "%ML_SERVICE_SCRIPT%"
    pause
    exit /b 1
)

if /I "%~1"=="--single" goto :single
if /I "%~1"=="-single" goto :single
if /I "%~1"=="/single" goto :single

echo Starting ML service in a new terminal...
start "ML Service - FastAPI" cmd /k "call ""%ML_SERVICE_SCRIPT%"""

echo Starting backend in a new terminal...
start "Backend - Laravel" cmd /k "cd /d ""%BACKEND_DIR%"" && php artisan serve --host=0.0.0.0 --port=8000"

echo Starting Laravel Reverb WebSocket server...
start "WebSocket - Reverb" cmd /k "cd /d ""%BACKEND_DIR%"" && php artisan reverb:start --host=0.0.0.0 --port=8080"

echo Starting Laravel scheduler worker...
start "Scheduler - Laravel" cmd /k "cd /d ""%BACKEND_DIR%"" && php artisan schedule:work"

echo Starting Laravel queue worker...
start "Queue Worker - Laravel" cmd /k "cd /d ""%BACKEND_DIR%"" && php artisan queue:work --tries=1 --sleep=3"

echo Starting frontend in a new terminal...
start "Frontend - Vite Dev" cmd /k "cd /d ""%FRONTEND_DIR%"" && set NODE_OPTIONS=--max-old-space-size=4096 && npm run dev"

echo.
echo All services were launched.
echo You can close this launcher window.

goto :eof

:single
echo Starting ML service in background (hidden window)...
start "" /min cmd /c "call ""%ML_SERVICE_SCRIPT%"""

echo Starting backend in background (hidden window)...
start "" /min cmd /c "cd /d ""%BACKEND_DIR%"" && php artisan serve --host=0.0.0.0 --port=8000"

echo Starting Laravel Reverb in background (hidden window)...
start "" /min cmd /c "cd /d ""%BACKEND_DIR%"" && php artisan reverb:start --host=0.0.0.0 --port=8080"

echo Starting Laravel scheduler in background (hidden window)...
start "" /min cmd /c "cd /d ""%BACKEND_DIR%"" && php artisan schedule:work"

echo Starting Laravel queue worker in background (hidden window)...
start "" /min cmd /c "cd /d ""%BACKEND_DIR%"" && php artisan queue:work --tries=1 --sleep=3"

echo Starting frontend in current terminal...
cd /d "%FRONTEND_DIR%"
set NODE_OPTIONS=--max-old-space-size=4096
npm run dev

endlocal