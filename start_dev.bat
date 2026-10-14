@echo off
chcp 65001 > nul
setlocal

rem Dung chung mot luong khoi dong de tranh lech cong va khoi dong trung service.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start_dev.ps1"

if errorlevel 1 (
    echo.
    echo Khoi dong that bai. Nhan phim bat ky de dong cua so...
    pause >nul
)
