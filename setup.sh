#!/bin/bash

# Kyukei-Panda Quick Setup Script
# This script fixes common configuration issues and sets up the application

echo "🐼 Kyukei-Panda Quick Setup"
echo "=========================="
echo ""

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script from the Laravel project root."
    exit 1
fi

# Create .env file if it doesn't exist
if [ ! -f ".env" ]; then
    echo "📝 Creating .env file..."
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo "✅ .env file created from .env.example"
    else
        echo "❌ .env.example not found. Please create .env file manually."
        exit 1
    fi
fi

# Install dependencies if needed
if [ ! -d "vendor" ]; then
    echo "📦 Installing PHP dependencies..."
    composer install
fi

# Generate app key if needed
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Generating application key..."
    php artisan key:generate
fi

# Fix Pusher configuration by setting safe defaults
echo "🔧 Fixing Pusher configuration..."
sed -i.bak 's/PUSHER_APP_ID=$/PUSHER_APP_ID=kyukei-panda-app/' .env
sed -i.bak 's/PUSHER_APP_KEY=$/PUSHER_APP_KEY=kyukei-panda-key/' .env
sed -i.bak 's/PUSHER_APP_SECRET=$/PUSHER_APP_SECRET=kyukei-panda-secret/' .env

# Set broadcast driver to null to avoid Pusher errors
sed -i.bak 's/BROADCAST_DRIVER=.*/BROADCAST_DRIVER=null/' .env

# Set up SQLite database for easy setup
echo "🗄️ Setting up SQLite database..."
sed -i.bak 's/DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
sed -i.bak 's/DB_DATABASE=.*/DB_DATABASE=database\/kyukei_panda.sqlite/' .env

# Create SQLite database file
mkdir -p database
touch database/kyukei_panda.sqlite

# Set cache and session to file for simplicity
sed -i.bak 's/CACHE_DRIVER=.*/CACHE_DRIVER=file/' .env
sed -i.bak 's/SESSION_DRIVER=.*/SESSION_DRIVER=file/' .env
sed -i.bak 's/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=database/' .env

# Run the setup command
echo "🚀 Running Kyukei-Panda setup..."
php artisan kyukei-panda:setup --force

# Create storage directories
echo "📁 Setting up storage directories..."
php artisan storage:link

# Run migrations
echo "🏗️ Running database migrations..."
php artisan migrate --force

# Clear and cache configuration
echo "⚡ Optimizing application..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Set proper permissions
echo "🔐 Setting file permissions..."
chmod -R 775 storage bootstrap/cache
if command -v chown &> /dev/null; then
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
fi

echo ""
echo "🎉 Setup completed successfully!"
echo ""
echo "📋 Configuration Summary:"
echo "  - Database: SQLite (database/kyukei_panda.sqlite)"
echo "  - Cache: File-based"
echo "  - Broadcasting: Disabled (null driver)"
echo "  - Queue: Database"
echo ""
echo "🌐 You can now start the application with:"
echo "  php artisan serve"
echo ""
echo "🔧 To enable real-time features:"
echo "  1. Configure Pusher credentials in .env"
echo "  2. Set BROADCAST_DRIVER=pusher"
echo "  3. Run: php artisan config:cache"
echo ""
echo "📚 For more information, visit: https://docs.kyukei-panda.com"
echo ""
echo "🐼 Happy productivity tracking!"
