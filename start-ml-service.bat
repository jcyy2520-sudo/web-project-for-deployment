@echo off
setlocal

set "ROOT=%~dp0"
set "ML_SERVICE_DIR=%ROOT%ml-service"
set "PYTHON_EXE=%ROOT%.venv\Scripts\python.exe"
set "MAIN_PY=%ML_SERVICE_DIR%\main.py"

if not exist "%PYTHON_EXE%" (
    echo [ERROR] Python virtual environment not found: "%PYTHON_EXE%"
    echo Create or restore the workspace venv before starting the ML service.
    exit /b 1
)

if not exist "%MAIN_PY%" (
    echo [ERROR] ML service entrypoint not found: "%MAIN_PY%"
    exit /b 1
)

cd /d "%ML_SERVICE_DIR%"
"%PYTHON_EXE%" "%MAIN_PY%"

endlocal