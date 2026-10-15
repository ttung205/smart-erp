@echo off
chcp 65001 > nul
setlocal

rem PowerShell se mo mot cua so Windows Terminal ten SmartERP, moi service nam trong mot tab.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start_dev.ps1"

if errorlevel 1 (
    echo.
    echo Khoi dong that bai. Nhan phim bat ky de dong cua so...
    pause >nul
)
