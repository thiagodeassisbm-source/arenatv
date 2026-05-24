@echo off
chcp 65001 >nul
cd /d "%~dp0"
title Instalar banco venda_canais

set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
if not exist "%MYSQL%" (
    echo [ERRO] Nao encontrado: %MYSQL%
    echo Instale o XAMPP em C:\xampp
    pause
    exit /b 1
)

echo.
echo  Criando banco e importando database.sql...
echo.

"%MYSQL%" -h127.0.0.1 -P3306 -uroot --connect-timeout=10 -e "CREATE DATABASE IF NOT EXISTS venda_canais DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if %ERRORLEVEL% neq 0 (
    echo.
    echo  [ERRO] Nao conectou ao MySQL.
    echo  Execute primeiro: corrigir_mysql.bat
    echo  Depois Start no MySQL no XAMPP.
    pause
    exit /b 1
)

"%MYSQL%" -h127.0.0.1 -P3306 -uroot venda_canais --connect-timeout=10 < "%~dp0database.sql"
if %ERRORLEVEL% neq 0 (
    echo  [AVISO] Alguns comandos SQL podem ter falhado se o banco ja existia.
)

echo.
echo  Pronto! Banco: venda_canais
echo  Agora execute: iniciar.bat
echo  Acesse: http://127.0.0.1:8000
echo.
pause
