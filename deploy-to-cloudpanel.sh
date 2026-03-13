#!/bin/bash

# CloudPanel Deployment Script for Rotational Contribution App
# This script automates the deployment process

set -e

echo "========================================="
echo "Rotational Contribution App Deployment"
echo "========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

# Check if running as site user
if [ "$EUID" -eq 0 ]; then 
    print_error "Do not run this script as root. Run as your CloudPanel site user."
    exit 1
fi

# Get current directory
DEPLOY_DIR=$(pwd)
print_info "Deployment directory: $DEPLOY_DIR"

# Step 1: Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "artisan file not found. Are you in the Laravel root directory?"
    exit 1
fi

print_success "Found Laravel application"

# Step 2: Put application in maintenance mode
print_info "Putting application in maintenance mode..."
php artisan down || true
print_success "Application in maintenance mode"

# Step 3: Pull latest code (if using git)
if [ -d ".git" ]; then
    print_info "Pulling latest code from git..."
    git pull origin main
    print_success "Code updated"
else
    print_info "Not a git repository, skipping git pull"
fi

# Step 4: Install/Update Composer dependencies
print_info "Installing Composer dependencies..."
composer install --optimize-autoloader --no-dev --no-scripts
print_success "Composer dependencies installed"

# Step 5: Run database migrations
print_info "Running database migrations..."
php artisan migrate --force
print_success "Database migrations completed"

# Step 6: Clear and cache configuration
print_info "Clearing old cache..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

print_info "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
print_success "Configuration cached"

# Step 7: Create storage link if it doesn't exist
if [ ! -L "public/storage" ]; then
    print_info "Creating storage link..."
    php artisan storage:link
    print_success "Storage link created"
fi

# Step 8: Set correct permissions
print_info "Setting file permissions..."
chmod -R 755 storage bootstrap/cache
print_success "Permissions set"

# Step 9: Restart queue workers (if supervisor is configured)
if command -v supervisorctl &> /dev/null; then
    print_info "Restarting queue workers..."
    sudo supervisorctl restart rotational-worker:* 2>/dev/null || print_info "Queue workers not configured yet"
fi

# Step 10: Bring application back online
print_info "Bringing application back online..."
php artisan up
print_success "Application is now live!"

echo ""
echo "========================================="
echo "Deployment completed successfully! 🎉"
echo "========================================="
echo ""
print_info "Next steps:"
echo "  1. Test your application at your domain"
echo "  2. Check logs: tail -f storage/logs/laravel.log"
echo "  3. Monitor queue workers: sudo supervisorctl status"
echo ""
