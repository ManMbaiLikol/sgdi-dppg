@echo off
REM Script d'export de la base de données pour Railway
REM Auteur: SGDI Team

echo ========================================
echo Export Base de Donnees SGDI pour Railway
echo ========================================
echo.

REM Configuration
set MYSQL_PATH=C:\wamp64\bin\mysql\mysql8.0.31\bin
set DB_NAME=sgdi_mvp
set DB_USER=root
set OUTPUT_FILE=database\sgdi_mvp_railway_export.sql

echo 1. Detection de MySQL...
if not exist "%MYSQL_PATH%\mysqldump.exe" (
    echo ERREUR: MySQL non trouve a %MYSQL_PATH%
    echo.
    echo Veuillez modifier MYSQL_PATH dans ce script selon votre version de WAMP
    echo Exemple: C:\wamp64\bin\mysql\mysql8.0.31\bin
    echo.
    pause
    exit /b 1
)

echo MySQL trouve: %MYSQL_PATH%
echo.

echo 2. Export de la base de donnees %DB_NAME%...
echo.

REM Demander le mot de passe (vide pour WAMP par défaut)
set /p DB_PASSWORD="Mot de passe MySQL (Entree si vide): "

REM Export avec options optimisées pour Railway
"%MYSQL_PATH%\mysqldump.exe" ^
    --user=%DB_USER% ^
    --password=%DB_PASSWORD% ^
    --host=localhost ^
    --port=3306 ^
    --single-transaction ^
    --routines ^
    --triggers ^
    --add-drop-table ^
    --complete-insert ^
    --default-character-set=utf8mb4 ^
    %DB_NAME% > "%OUTPUT_FILE%"

if %ERRORLEVEL% equ 0 (
    echo.
    echo ========================================
    echo SUCCES!
    echo ========================================
    echo.
    echo Fichier cree: %OUTPUT_FILE%
    echo.
    dir "%OUTPUT_FILE%" | findstr /C:"%OUTPUT_FILE%"
    echo.
    echo Prochaines etapes:
    echo 1. Ouvrir HeidiSQL ou MySQL Workbench
    echo 2. Se connecter a Railway (voir database\IMPORT_RAILWAY.md)
    echo 3. Importer le fichier: %OUTPUT_FILE%
    echo.
    echo Guide complet: database\IMPORT_RAILWAY.md
    echo ========================================
) else (
    echo.
    echo ERREUR lors de l'export!
    echo Verifiez:
    echo - Le mot de passe MySQL
    echo - Que WAMP est demarre
    echo - Que la base %DB_NAME% existe
)

echo.
pause
