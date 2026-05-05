@echo off
REM ML Service Auto-Start Setup
REM This creates a Windows Task Scheduler job to run the ML service at startup

set "ROOT=%~dp0"
set "START_SCRIPT=%ROOT%start-ml-service.bat"

if not exist "%START_SCRIPT%" (
    echo ERROR: ML launcher not found at %START_SCRIPT%
    pause
    exit /b 1
)

schtasks /create /tn "MLService" /tr "%START_SCRIPT%" /sc onstart /ru SYSTEM /rl highest /f

if %errorLevel% equ 0 (
    echo.
    echo ML Service scheduled task created successfully!
    echo.
    echo The ML service will now:
    echo - Start automatically when Windows boots
    echo - Restart if it crashes
    echo - Run in the background (no terminal window)
    echo.
    echo Test it now:
    schtasks /run /tn "MLService"
    echo.
    timeout /t 3
    echo Testing health endpoint...
    curl http://127.0.0.1:8100/health
    echo.
) else (
    echo ERROR: Failed to create task
)

pause
