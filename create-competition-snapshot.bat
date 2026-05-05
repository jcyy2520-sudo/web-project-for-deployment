@echo off
setlocal

powershell -ExecutionPolicy Bypass -File "%~dp0create-competition-snapshot.ps1" %*

endlocal
