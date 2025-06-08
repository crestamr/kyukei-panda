@echo off
setlocal enabledelayedexpansion

echo 🐼 Kyukei-Panda Quick Setup
echo ==========================
echo.

REM Check if we're in the right directory
if not exist "artisan" (
    echo ❌ Error: artisan file not found. Please run this script from the Laravel project root.
    pause
    exit /b 1
)

REM Create .env file if it doesn't exist
if not exist ".env" (
    echo 📝 Creating .env file...
    if exist ".env.example" (
        copy ".env.example" ".env" >nul
        echo ✅ .env file created from .env.example
    ) else (
        echo ❌ .env.example not found. Please create .env file manually.
        pause
        exit /b 1
    )
)

REM Install dependencies if needed
if not exist "vendor" (
    echo 📦 Installing PHP dependencies...
    composer install
)

REM Generate app key if needed
findstr /C:"APP_KEY=base64:" .env >nul
if errorlevel 1 (
    echo 🔑 Generating application key...
    php artisan key:generate
)

REM Fix Pusher configuration by setting safe defaults
echo 🔧 Fixing Pusher configuration...
powershell -Command "(Get-Content .env) -replace 'PUSHER_APP_ID=$', 'PUSHER_APP_ID=kyukei-panda-app' | Set-Content .env"
powershell -Command "(Get-Content .env) -replace 'PUSHER_APP_KEY=$', 'PUSHER_APP_KEY=kyukei-panda-key' | Set-Content .env"
powershell -Command "(Get-Content .env) -replace 'PUSHER_APP_SECRET=$', 'PUSHER_APP_SECRET=kyukei-panda-secret' | Set-Content .env"

REM Set broadcast driver to null to avoid Pusher errors
powershell -Command "(Get-Content .env) -replace 'BROADCAST_DRIVER=.*', 'BROADCAST_DRIVER=null' | Set-Content .env"

REM Set up SQLite database for easy setup
echo 🗄️ Setting up SQLite database...
powershell -Command "(Get-Content .env) -replace 'DB_CONNECTION=.*', 'DB_CONNECTION=sqlite' | Set-Content .env"
powershell -Command "(Get-Content .env) -replace 'DB_DATABASE=.*', 'DB_DATABASE=database/kyukei_panda.sqlite' | Set-Content .env"

REM Create SQLite database file
if not exist "database" mkdir database
if not exist "database\kyukei_panda.sqlite" (
    type nul > "database\kyukei_panda.sqlite"
)

REM Set cache and session to file for simplicity
powershell -Command "(Get-Content .env) -replace 'CACHE_DRIVER=.*', 'CACHE_DRIVER=file' | Set-Content .env"
powershell -Command "(Get-Content .env) -replace 'SESSION_DRIVER=.*', 'SESSION_DRIVER=file' | Set-Content .env"
powershell -Command "(Get-Content .env) -replace 'QUEUE_CONNECTION=.*', 'QUEUE_CONNECTION=database' | Set-Content .env"

REM Run the setup command
echo 🚀 Running Kyukei-Panda setup...
php artisan kyukei-panda:setup --force

REM Create storage link
echo 📁 Setting up storage directories...
php artisan storage:link

REM Run migrations
echo 🏗️ Running database migrations...
php artisan migrate --force

REM Clear and cache configuration
echo ⚡ Optimizing application...
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

echo.
echo 🎉 Setup completed successfully!
echo.
echo 📋 Configuration Summary:
echo   - Database: SQLite (database/kyukei_panda.sqlite)
echo   - Cache: File-based
echo   - Broadcasting: Disabled (null driver)
echo   - Queue: Database
echo.
echo 🌐 You can now start the application with:
echo   php artisan serve
echo.
echo 🔧 To enable real-time features:
echo   1. Configure Pusher credentials in .env
echo   2. Set BROADCAST_DRIVER=pusher
echo   3. Run: php artisan config:cache
echo.
echo 📚 For more information, visit: https://docs.kyukei-panda.com
echo.
echo 🐼 Happy productivity tracking!
echo.
pause
