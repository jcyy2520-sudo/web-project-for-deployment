@echo off
REM ML Service - Simple Windows Task Scheduler Setup
REM Run this as Administrator

setlocal enabledelayedexpansion

echo.
echo ========================================
echo ML Service - Task Scheduler Setup
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

set PYTHON_EXE=C:\Users\ASUS\AppData\Local\Programs\Python\Python313\python.exe
set ML_SERVICE_DIR=c:\laragon\www\web\ml-service
set MAIN_PY=%ML_SERVICE_DIR%\main.py
set TASK_NAME=MLService

echo Python: %PYTHON_EXE%
echo Service: %MAIN_PY%
echo.

REM Verify Python exists
if not exist "%PYTHON_EXE%" (
    echo ERROR: Python not found at %PYTHON_EXE%
    pause
    exit /b 1
)

REM Verify main.py exists
if not exist "%MAIN_PY%" (
    echo ERROR: main.py not found at %MAIN_PY%
    pause
    exit /b 1
)

echo Creating scheduled task...
echo.

REM Delete existing task if it exists
schtasks /delete /tn "%TASK_NAME%" /f >nul 2>&1

REM Create new task to run at system startup
schtasks /create /tn "%TASK_NAME%" /tr "\"%PYTHON_EXE%\" \"%MAIN_PY%\"" ^
    /sc onstart /ru SYSTEM /rl highest /f

if %errorLevel% equ 0 (
    echo.
    echo ========================================
    echo SUCCESS! Task created
    echo ========================================
    echo.
    echo Task Name: %TASK_NAME%
    echo Service will start automatically at system startup
    echo.
    echo Common commands:
    echo   schtasks /run /tn "%TASK_NAME%"      - Start now
    echo   schtasks /end /tn "%TASK_NAME%"      - Stop task
    echo   schtasks /delete /tn "%TASK_NAME%"   - Remove task
    echo.
    echo Starting the service now...
    schtasks /run /tn "%TASK_NAME%"
    timeout /t 3 /nobreak
    echo.
    echo Test the service: curl http://127.0.0.1:8100/health
    echo.
) else (
    echo ERROR: Failed to create task
    pause
    exit /b 1
)

pause
