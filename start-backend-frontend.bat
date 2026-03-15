@echo off
setlocal

rem Resolve project root from this script location
set "ROOT=%~dp0"
set "BACKEND_DIR=%ROOT%web-backend"
set "FRONTEND_DIR=%ROOT%web-frontend"

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

if /I "%~1"=="--single" goto :single
if /I "%~1"=="-single" goto :single
if /I "%~1"=="/single" goto :single

echo Starting backend in a new terminal...
start "Backend - Laravel" cmd /k "cd /d ""%BACKEND_DIR%"" && php artisan serve"

echo Starting frontend in a new terminal...
start "Frontend - Vite Dev" cmd /k "cd /d ""%FRONTEND_DIR%"" && set NODE_OPTIONS=--max-old-space-size=4096 && npm run dev"

echo.
echo Both terminals were launched.
echo You can close this launcher window.

goto :eof

:single
echo Starting backend in background (hidden window)...
start "" /min cmd /c "cd /d ""%BACKEND_DIR%"" && php artisan serve"

echo Starting frontend in current terminal...
cd /d "%FRONTEND_DIR%"
set NODE_OPTIONS=--max-old-space-size=4096
npm run dev

endlocal