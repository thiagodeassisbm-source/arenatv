@echo off
chcp 65001 >nul
cd /d "%~dp0"
title Arena Stream

set "PHP_EXE="
if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE where php >nul 2>&1 && set "PHP_EXE=php"

if not defined PHP_EXE (
    echo [ERRO] PHP nao encontrado.
    pause
    exit /b 1
)

if not exist "storage" mkdir storage

echo.
echo  ========================================
echo   ARENA STREAM
echo  ========================================
echo   Banco: SQLite (storage\venda_canais.sqlite)
echo   NAO precisa de XAMPP nem MySQL!
echo.
echo   Site:  http://127.0.0.1:8000
echo   Debug streams: http://127.0.0.1:8000/debug_stream.php
echo.
echo   NAO FECHE esta janela.
echo  ========================================
echo.

for /f "tokens=5" %%a in ('netstat -ano ^| findstr "127.0.0.1:8000" ^| findstr LISTENING') do taskkill /F /PID %%a >nul 2>&1
for /f "tokens=5" %%a in ('netstat -ano ^| findstr "127.0.0.1:8787" ^| findstr LISTENING') do taskkill /F /PID %%a >nul 2>&1
timeout /t 1 /nobreak >nul

where node >nul 2>&1
if %errorlevel%==0 (
    echo   Proxy stream: http://127.0.0.1:8787  ^(Node.js^)
    start "Arena Stream Proxy" /min node "%CD%\stream-proxy\server.js"
) else (
    echo   Proxy Node: nao encontrado ^(usa so PHP^)
)

echo.
start "" "http://127.0.0.1:8000/"
"%PHP_EXE%" -S 127.0.0.1:8000 -t "%CD%"
pause
