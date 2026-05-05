@echo off
setlocal

echo ==========================================
echo Competition Health Check
echo ==========================================
echo.

set "BACKEND_OK=0"
set "ML_OK=0"
set "QUEUE_OK=0"
set "REVERB_OK=0"
set "EXIT_CODE=0"

echo [1/4] Checking backend health...
powershell -NoProfile -Command "try { $r = Invoke-WebRequest -UseBasicParsing 'http://127.0.0.1:8000/api/health' -TimeoutSec 5; if ($r.StatusCode -eq 200) { exit 0 } else { exit 1 } } catch { exit 1 }"
if errorlevel 1 (
    echo   FAIL - Backend health endpoint is not responding on port 8000.
) else (
    echo   PASS - Backend health endpoint responded.
    set "BACKEND_OK=1"
)
echo.

echo [2/4] Checking ML service health...
powershell -NoProfile -Command "try { $r = Invoke-WebRequest -UseBasicParsing 'http://127.0.0.1:8100/health' -TimeoutSec 5; if ($r.StatusCode -eq 200) { exit 0 } else { exit 1 } } catch { exit 1 }"
if errorlevel 1 (
    echo   FAIL - ML service health endpoint is not responding on port 8100.
) else (
    echo   PASS - ML service health endpoint responded.
    set "ML_OK=1"
)
echo.

echo [3/4] Checking queue worker process...
powershell -NoProfile -Command "$p = Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match 'php artisan queue:work' }; if ($p) { exit 0 } else { exit 1 }"
if errorlevel 1 (
    echo   FAIL - No running queue worker process was detected.
) else (
    echo   PASS - Queue worker process detected.
    set "QUEUE_OK=1"
)
echo.

echo [4/4] Checking Reverb process...
powershell -NoProfile -Command "$p = Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match 'php artisan reverb:start' }; if ($p) { exit 0 } else { exit 1 }"
if errorlevel 1 (
    echo   FAIL - No running Reverb process was detected.
) else (
    echo   PASS - Reverb process detected.
    set "REVERB_OK=1"
)
echo.

echo ==========================================
echo Summary
echo ==========================================

if "%BACKEND_OK%"=="1" (
    echo Backend: PASS
) else (
    echo Backend: FAIL
)

if "%ML_OK%"=="1" (
    echo ML Service: PASS
) else (
    echo ML Service: FAIL
)

if "%QUEUE_OK%"=="1" (
    echo Queue Worker: PASS
) else (
    echo Queue Worker: FAIL
)

if "%REVERB_OK%"=="1" (
    echo Reverb: PASS
) else (
    echo Reverb: FAIL
)

echo.
if "%BACKEND_OK%%ML_OK%%QUEUE_OK%%REVERB_OK%"=="1111" (
    echo System looks ready for a demo pass.
) else (
    echo One or more critical services are not ready. Fix the failed items before the competition.
    set "EXIT_CODE=1"
)

endlocal & exit /b %EXIT_CODE%