@echo off
setlocal
chcp 65001 >nul
cd /d "%~dp0"

where pwsh >nul 2>nul
if %errorlevel%==0 (
    pwsh -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\demo\demo.ps1" %*
) else (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\demo\demo.ps1" %*
)

set "EXITCODE=%errorlevel%"
echo.
if not defined FIXPOINT_NO_PAUSE pause
exit /b %EXITCODE%
