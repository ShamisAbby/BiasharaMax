@echo off
REM Sets up backend\ for local development on Windows. Wraps the steps in
REM backend\README.md.

setlocal
set ROOT_DIR=%~dp0..
cd /d "%ROOT_DIR%\backend"

echo ==^> Installing PHP dependencies
call composer install
if errorlevel 1 exit /b 1

echo ==^> Installing JS dependencies
call npm install
if errorlevel 1 exit /b 1

if not exist ".env" (
    echo ==^> Creating .env from .env.example
    copy .env.example .env
    call php artisan key:generate
) else (
    echo ==^> .env already exists, skipping key:generate
)

echo ==^> Running migrations ^(with seed data^)
call php artisan migrate --seed
if errorlevel 1 exit /b 1

echo ==^> Building frontend assets
call npm run build

echo.
echo Done. Start the app with:
echo     cd backend ^&^& composer run dev
echo.

endlocal
