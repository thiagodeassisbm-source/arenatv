@echo off
chcp 65001 >nul
title Corrigir MySQL travado (XAMPP)

echo.
echo  ============================================
echo   CORRIGIR MYSQL TRAVADO
echo  ============================================
echo.
echo  Seu MySQL aceita a porta 3306 mas NAO responde
echo  (erro de handshake). Este script encerra o
echo  processo travado para voce iniciar de novo.
echo.
pause

echo.
echo  [1/3] Encerrando MySQL no XAMPP (se estiver aberto)...
taskkill /F /IM mysqld.exe >nul 2>&1
taskkill /F /IM mysql.exe >nul 2>&1

echo  [2/3] Aguardando 8 segundos...
timeout /t 8 /nobreak >nul

echo  [3/3] Testando conexao...
if not exist "C:\xampp\mysql\bin\mysql.exe" (
    echo  AVISO: XAMPP nao encontrado em C:\xampp
    goto fim
)

"C:\xampp\mysql\bin\mysql.exe" -h127.0.0.1 -P3306 -uroot --connect-timeout=5 -e "SELECT 'MySQL OK' AS status;" 2>nul
if %ERRORLEVEL%==0 (
    echo.
    echo  SUCESSO! MySQL respondeu.
    echo  Agora rode: instalar_banco.bat
    echo  Depois: iniciar.bat
) else (
    echo.
    echo  Ainda com erro. Faca manualmente:
    echo  1. Abra o XAMPP Control Panel
    echo  2. Clique START no MySQL
    echo  3. Se falhar, veja Logs do MySQL no XAMPP
    echo  4. Reinicie o computador e tente de novo
)

:fim
echo.
pause
