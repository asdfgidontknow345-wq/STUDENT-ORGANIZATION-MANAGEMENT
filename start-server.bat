@echo off
title Student Org Management and Event Registration System
echo ======================================================================
echo   CampusSync: Student Org. Management & Event Registration System
echo ======================================================================
echo.

set PHP_EXE=php
where php >nul 2>nul
if %errorlevel% neq 0 (
    if exist "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" (
        set "PHP_EXE=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
    ) else if exist "C:\Program Files\PHP\php.exe" (
        set "PHP_EXE=C:\Program Files\PHP\php.exe"
    ) else if exist "C:\php\php.exe" (
        set "PHP_EXE=C:\php\php.exe"
    ) else if exist "C:\xampp\php\php.exe" (
        set "PHP_EXE=C:\xampp\php\php.exe"
    ) else (
        echo [ERROR] PHP executable could not be found!
        echo Please ensure PHP is installed and added to PATH.
        pause
        exit /b 1
    )
)

:: Automatically detect local network IPv4 address for multi-device access
set LOCAL_IP=
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4 Address"') do (
    for /f "tokens=* delims= " %%b in ("%%a") do (
        if not defined LOCAL_IP set "LOCAL_IP=%%b"
    )
)

echo Starting PHP server on 0.0.0.0:8000 (Local and Network enabled)...
echo Database: Automatic MySQL / SQLite fallback enabled.
echo.
echo ======================================================================
echo   ACCESS URLS:
echo   - On this laptop:       http://localhost:8000
if defined LOCAL_IP (
    echo   - From other laptops:   http://%LOCAL_IP%:8000
) else (
    echo   - From other laptops:   http://[YOUR-IP-ADDRESS]:8000
)
echo ======================================================================
echo.
echo   NOTE FOR OTHER LAPTOPS:
echo   1. Both laptops must be connected to the SAME Wi-Fi or Hotspot.
echo   2. If Windows Defender Firewall prompts, click "Allow access".
echo.
echo Demo Accounts:
echo   - Student: student@university.edu (Password: password123)
echo   - Admin:   admin@university.edu   (Password: password123)
echo.
echo Opening browser...
start http://localhost:8000
echo Server is running. Press Ctrl+C in this window to stop the server.
echo.

"%PHP_EXE%" -c php.ini -S 0.0.0.0:8000
pause
