@echo off
title CampusSync - Public Internet Sharing (Any Network)
color 0B
echo ======================================================================
echo       CampusSync: Public Internet Sharing (Any Network / Anywhere)
echo ======================================================================
echo.

:: 1. Ensure cloudflared.exe exists
if not exist "cloudflared.exe" (
    echo [INFO] cloudflared.exe not found. Downloading Cloudflare Tunnel...
    curl -L -o "cloudflared.exe" "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe"
    if not exist "cloudflared.exe" (
        echo [ERROR] Failed to download cloudflared.exe. Please check your internet connection.
        pause
        exit /b 1
    )
)

:: 2. Check if local server is running on port 8000
netstat -ano | findstr ":8000" | findstr "LISTENING" >nul
if %errorlevel% neq 0 (
    echo [INFO] Local PHP server is not currently running.
    echo Starting local server in a separate background window...
    start "CampusSync PHP Server" /min cmd /c "start-server.bat"
    timeout /t 3 /nobreak >nul
)

echo.
echo ======================================================================
echo   CREATING SECURE PUBLIC HTTPS LINK VIA CLOUDFLARE...
echo ======================================================================
echo.
echo   Look below for your public link:
echo   --> https://[random-name].trycloudflare.com
echo.
echo   You can copy that link and send it to ANYONE on ANY network!
echo   (Press Ctrl+C in this window when you want to stop sharing)
echo ======================================================================
echo.

.\cloudflared.exe tunnel --url http://localhost:8000
pause
