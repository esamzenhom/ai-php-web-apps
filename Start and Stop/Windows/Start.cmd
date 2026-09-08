@echo off
setlocal
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0..\..\application\bin\start-windows.ps1" %*
set "result=%errorlevel%"
pause
exit /b %result%
