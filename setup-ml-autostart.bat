@echo off
REM ML Service Auto-Start Setup
REM This creates a Windows Task Scheduler job to run the ML service at startup

schtasks /create /tn "MLService" /tr "C:\Users\ASUS\AppData\Local\Programs\Python\Python313\python.exe c:\laragon\www\web\ml-service\main.py" /sc onstart /ru SYSTEM /rl highest /f

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
