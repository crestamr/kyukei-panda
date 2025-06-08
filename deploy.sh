#!/bin/bash

# Kyukei-Panda Enterprise Deployment Script
# This script automates the deployment process for production environments

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="kyukei-panda"
DEPLOY_USER="deploy"
BACKUP_DIR="/var/backups/kyukei-panda"
LOG_FILE="/var/log/kyukei-panda-deploy.log"

# Functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}✓${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}⚠${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}✗${NC} $1" | tee -a "$LOG_FILE"
    exit 1
}

# Check if running as correct user
check_user() {
    if [[ $EUID -eq 0 ]]; then
        error "This script should not be run as root. Please run as the deploy user."
    fi
    
    if [[ $(whoami) != "$DEPLOY_USER" ]]; then
        warning "Running as $(whoami), expected $DEPLOY_USER"
    fi
}

# Pre-deployment checks
pre_deployment_checks() {
    log "Running pre-deployment checks..."
    
    # Check if required commands exist
    command -v php >/dev/null 2>&1 || error "PHP is not installed"
    command -v composer >/dev/null 2>&1 || error "Composer is not installed"
    command -v npm >/dev/null 2>&1 || error "NPM is not installed"
    command -v redis-cli >/dev/null 2>&1 || error "Redis is not installed"
    command -v psql >/dev/null 2>&1 || error "PostgreSQL client is not installed"
    
    # Check PHP version
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    if [[ $(echo "$PHP_VERSION" | cut -d. -f1) -lt 8 ]]; then
        error "PHP 8.0 or higher is required. Current version: $PHP_VERSION"
    fi
    
    # Check disk space (require at least 1GB free)
    AVAILABLE_SPACE=$(df / | awk 'NR==2 {print $4}')
    if [[ $AVAILABLE_SPACE -lt 1048576 ]]; then
        error "Insufficient disk space. At least 1GB required."
    fi
    
    success "Pre-deployment checks passed"
}

# Create backup
create_backup() {
    log "Creating backup..."
    
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_PATH="$BACKUP_DIR/backup_$TIMESTAMP"
    
    # Create backup directory
    mkdir -p "$BACKUP_PATH"
    
    # Backup application files
    if [[ -d "/var/www/$APP_NAME" ]]; then
        log "Backing up application files..."
        tar -czf "$BACKUP_PATH/app_files.tar.gz" -C "/var/www" "$APP_NAME"
    fi
    
    # Backup database
    log "Backing up database..."
    if [[ -n "$DB_DATABASE" ]]; then
        pg_dump -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" > "$BACKUP_PATH/database.sql"
    fi
    
    # Backup environment file
    if [[ -f "/var/www/$APP_NAME/.env" ]]; then
        cp "/var/www/$APP_NAME/.env" "$BACKUP_PATH/.env.backup"
    fi
    
    success "Backup created at $BACKUP_PATH"
    echo "$BACKUP_PATH" > /tmp/kyukei_panda_backup_path
}

# Deploy application
deploy_application() {
    log "Deploying application..."
    
    APP_DIR="/var/www/$APP_NAME"
    
    # Pull latest code
    log "Pulling latest code from repository..."
    cd "$APP_DIR"
    git fetch origin
    git reset --hard origin/main
    
    # Install PHP dependencies
    log "Installing PHP dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
    
    # Install Node.js dependencies
    log "Installing Node.js dependencies..."
    npm ci --production
    
    # Build frontend assets
    log "Building frontend assets..."
    npm run build
    
    # Set proper permissions
    log "Setting file permissions..."
    sudo chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    sudo chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    
    success "Application deployed"
}

# Run database migrations
run_migrations() {
    log "Running database migrations..."
    
    cd "/var/www/$APP_NAME"
    
    # Check database connection
    php artisan migrate:status || error "Cannot connect to database"
    
    # Run migrations
    php artisan migrate --force
    
    # Seed production data if needed
    if [[ "$1" == "--seed" ]]; then
        log "Seeding database..."
        php artisan db:seed --class=ProductionSeeder
    fi
    
    success "Database migrations completed"
}

# Optimize application
optimize_application() {
    log "Optimizing application..."
    
    cd "/var/www/$APP_NAME"
    
    # Clear and cache configuration
    php artisan config:clear
    php artisan config:cache
    
    # Clear and cache routes
    php artisan route:clear
    php artisan route:cache
    
    # Clear and cache views
    php artisan view:clear
    php artisan view:cache
    
    # Optimize autoloader
    composer dump-autoload --optimize
    
    # Clear application cache
    php artisan cache:clear
    
    # Queue restart (if using queue workers)
    php artisan queue:restart
    
    success "Application optimized"
}

# Health check
health_check() {
    log "Performing health check..."
    
    # Check if application is responding
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost/api/ping")
    if [[ "$HTTP_STATUS" != "200" ]]; then
        error "Application health check failed. HTTP status: $HTTP_STATUS"
    fi
    
    # Check database connection
    cd "/var/www/$APP_NAME"
    php artisan migrate:status >/dev/null || error "Database connection failed"
    
    # Check Redis connection
    redis-cli ping >/dev/null || error "Redis connection failed"
    
    # Check disk space after deployment
    AVAILABLE_SPACE=$(df / | awk 'NR==2 {print $4}')
    if [[ $AVAILABLE_SPACE -lt 524288 ]]; then
        warning "Low disk space remaining: $(($AVAILABLE_SPACE / 1024))MB"
    fi
    
    success "Health check passed"
}

# Rollback function
rollback() {
    log "Rolling back deployment..."
    
    if [[ ! -f "/tmp/kyukei_panda_backup_path" ]]; then
        error "No backup path found. Cannot rollback."
    fi
    
    BACKUP_PATH=$(cat /tmp/kyukei_panda_backup_path)
    
    if [[ ! -d "$BACKUP_PATH" ]]; then
        error "Backup directory not found: $BACKUP_PATH"
    fi
    
    # Restore application files
    if [[ -f "$BACKUP_PATH/app_files.tar.gz" ]]; then
        log "Restoring application files..."
        cd "/var/www"
        sudo tar -xzf "$BACKUP_PATH/app_files.tar.gz"
    fi
    
    # Restore database
    if [[ -f "$BACKUP_PATH/database.sql" ]]; then
        log "Restoring database..."
        psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" < "$BACKUP_PATH/database.sql"
    fi
    
    # Restore environment file
    if [[ -f "$BACKUP_PATH/.env.backup" ]]; then
        cp "$BACKUP_PATH/.env.backup" "/var/www/$APP_NAME/.env"
    fi
    
    # Restart services
    sudo systemctl restart nginx
    sudo systemctl restart php8.1-fpm
    
    success "Rollback completed"
}

# Cleanup old backups
cleanup_backups() {
    log "Cleaning up old backups..."
    
    # Keep only last 5 backups
    find "$BACKUP_DIR" -name "backup_*" -type d | sort -r | tail -n +6 | xargs rm -rf
    
    success "Backup cleanup completed"
}

# Main deployment function
main() {
    log "Starting Kyukei-Panda deployment..."
    
    # Load environment variables
    if [[ -f "/var/www/$APP_NAME/.env" ]]; then
        source "/var/www/$APP_NAME/.env"
    fi
    
    # Parse command line arguments
    SEED_DB=false
    SKIP_BACKUP=false
    
    while [[ $# -gt 0 ]]; do
        case $1 in
            --seed)
                SEED_DB=true
                shift
                ;;
            --skip-backup)
                SKIP_BACKUP=true
                shift
                ;;
            --rollback)
                rollback
                exit 0
                ;;
            --help)
                echo "Usage: $0 [OPTIONS]"
                echo "Options:"
                echo "  --seed         Seed database after migration"
                echo "  --skip-backup  Skip backup creation"
                echo "  --rollback     Rollback to previous version"
                echo "  --help         Show this help message"
                exit 0
                ;;
            *)
                error "Unknown option: $1"
                ;;
        esac
    done
    
    # Run deployment steps
    check_user
    pre_deployment_checks
    
    if [[ "$SKIP_BACKUP" != true ]]; then
        create_backup
    fi
    
    # Set maintenance mode
    cd "/var/www/$APP_NAME"
    php artisan down --message="Deploying updates..." --retry=60
    
    # Trap to ensure maintenance mode is disabled on exit
    trap 'php artisan up' EXIT
    
    deploy_application
    
    if [[ "$SEED_DB" == true ]]; then
        run_migrations --seed
    else
        run_migrations
    fi
    
    optimize_application
    
    # Disable maintenance mode
    php artisan up
    trap - EXIT
    
    health_check
    cleanup_backups
    
    success "Deployment completed successfully!"
    log "Application is now live at: http://$(hostname -f)"
}

# Run main function
main "$@"
