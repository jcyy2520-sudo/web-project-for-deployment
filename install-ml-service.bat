@echo off
REM ML Service Windows Installation Script
REM Run this as Administrator

setlocal enabledelayedexpansion

echo.
echo ========================================
echo ML Service - Windows Installation
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
set SERVICE_NAME=MLService
set NSSM_URL=https://nssm.cc/download

echo Checking if NSSM is installed...
where nssm.exe >nul 2>&1

if %errorLevel% equ 0 (
    echo ✓ NSSM found in PATH
    set NSSM_EXE=nssm.exe
) else (
    echo NSSM not found. Downloading...

    REM Create temp directory
    set TEMP_DIR=%cd%\nssm-temp
    if not exist "!TEMP_DIR!" mkdir "!TEMP_DIR!"

    echo.
    echo ================================================
    echo MANUAL SETUP REQUIRED:
    echo ================================================
    echo 1. Download NSSM from: %NSSM_URL%
    echo 2. Extract nssm-*.exe to: !TEMP_DIR!
    echo 3. Run this script again
    echo.
    echo Or install NSSM using Chocolatey:
    echo    choco install nssm
    echo ================================================
    echo.
    pause
    exit /b 1
)

echo.
echo Python executable: %PYTHON_EXE%
echo ML Service directory: %ML_SERVICE_DIR%
echo Service name: %SERVICE_NAME%
echo.

REM Check if service already exists
%NSSM_EXE% status %SERVICE_NAME% >nul 2>&1

if %errorLevel% equ 0 (
    echo Found existing %SERVICE_NAME% service
    echo Removing old service...
    %NSSM_EXE% stop %SERVICE_NAME%
    %NSSM_EXE% remove %SERVICE_NAME% confirm
    echo ✓ Old service removed
    echo.
)

echo Creating new service...
%NSSM_EXE% install %SERVICE_NAME% "%PYTHON_EXE%" "%MAIN_PY%"

if %errorLevel% neq 0 (
    echo ERROR: Failed to create service
    pause
    exit /b 1
)

echo ✓ Service created

echo.
echo Configuring service...
%NSSM_EXE% set %SERVICE_NAME% AppDirectory "%ML_SERVICE_DIR%"
%NSSM_EXE% set %SERVICE_NAME% AppExit Default Restart
%NSSM_EXE% set %SERVICE_NAME% AppRestartDelay 5000
%NSSM_EXE% set %SERVICE_NAME% Type SERVICE
%NSSM_EXE% set %SERVICE_NAME% Start SERVICE_AUTO_START

echo ✓ Service configured

echo.
echo Starting service...
%NSSM_EXE% start %SERVICE_NAME%

timeout /t 2 /nobreak

%NSSM_EXE% status %SERVICE_NAME%

if %errorLevel% equ 0 (
    echo.
    echo ========================================
    echo SUCCESS! ML Service is now running
    echo ========================================
    echo.
    echo Service running on: http://127.0.0.1:8100
    echo.
    echo Common commands:
    echo   nssm status MLService     - Check status
    echo   nssm stop MLService       - Stop service
    echo   nssm start MLService      - Start service
    echo   nssm restart MLService    - Restart service
    echo   nssm remove MLService     - Remove service
    echo.
    echo Test the service:
    echo   curl http://127.0.0.1:8100/health
    echo.
) else (
    echo.
    echo ERROR: Failed to start service
    echo.
)

pause
