@echo off
setlocal

rem Resolve project root from this script location
set "ROOT=%~dp0"
set "ML_DIR=%ROOT%ml-service"
set "BACKEND_DIR=%ROOT%web-backend"
set "FRONTEND_DIR=%ROOT%web-frontend"

echo.
echo ========================================
echo Starting All Services
echo ========================================
echo.

rem Check if directories exist
if not exist "%ML_DIR%\main.py" (
    echo [WARNING] ML service folder not found: "%ML_DIR%"
)

if not exist "%BACKEND_DIR%\artisan" (
    echo [ERROR] Backend folder is not valid: "%BACKEND_DIR%"
    pause
    exit /b 1
)

if not exist "%FRONTEND_DIR%\package.json" (
    echo [ERROR] Frontend folder is not valid: "%FRONTEND_DIR%"
    pause
    exit /b 1
)

echo Starting Python ML Service in a new terminal...
start "ML Service - FastAPI" cmd /k "cd /d ""%ML_DIR%"" && python main.py"

echo Starting Backend in a new terminal...
start "Backend - Laravel" cmd /k "cd /d ""%BACKEND_DIR%"" && php artisan serve --host=0.0.0.0 --port=4000"

echo Starting Frontend in a new terminal...
start "Frontend - Vite Dev" cmd /k "cd /d ""%FRONTEND_DIR%"" && set NODE_OPTIONS=--max-old-space-size=4096 && npm run build && npm run dev"

echo.
echo ========================================
echo All services started in separate terminals
echo ML Service:  http://localhost:8000
echo Backend:    http://localhost:8000
echo Frontend:   http://localhost:5173
echo ========================================
echo.

endlocal
localhost