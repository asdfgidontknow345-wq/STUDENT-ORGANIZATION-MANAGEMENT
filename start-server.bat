@echo off
title Student Org Management and Event Registration System
echo ======================================================================
echo   Student Organization Management & Event Registration System
echo ======================================================================
echo.

set PHP_EXE=php
where php >nul 2>nul
if %errorlevel% neq 0 (
    if exist "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" (
        set "PHP_EXE=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
    ) else if exist "C:\xampp\php\php.exe" (
        set "PHP_EXE=C:\xampp\php\php.exe"
    ) else (
        echo [ERROR] PHP executable could not be found!
        echo Please ensure PHP is installed.
        pause
        exit /b 1
    )
)

echo Starting PHP built-in web server at http://localhost:8000 ...
echo Database: Automatic MySQL / SQLite fallback enabled.
echo.
echo Demo Accounts:
echo   - Student: student@university.edu (Password: password123)
echo   - Admin:   admin@university.edu   (Password: password123)
echo.
echo Opening browser...
start http://localhost:8000
echo Server is running. Press Ctrl+C in this window to stop the server.
echo.

"%PHP_EXE%" -c php.ini -S localhost:8000
pause

