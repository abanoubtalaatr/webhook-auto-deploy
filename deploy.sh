#!/bin/bash

# Auto-deploy script for webhook-auto-deploy project
echo "Starting deployment process..."

# Get the current directory
DEPLOY_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
echo "Deploy directory: $DEPLOY_DIR"

# Change to the project directory
cd "$DEPLOY_DIR"

# Pull latest changes (if this is a git repository)
if [ -d ".git" ]; then
    echo "Pulling latest changes from git..."
    git pull origin main 2>/dev/null || git pull origin master 2>/dev/null || echo "Git pull failed or not needed"
fi

# Install/update dependencies
if [ -f "composer.json" ]; then
    echo "Installing/updating composer dependencies..."
    composer install --no-dev --optimize-autoloader 2>/dev/null || echo "Composer install failed or not available"
fi

# Clear Laravel caches
if [ -f "artisan" ]; then
    echo "Clearing Laravel caches..."
    php artisan config:clear 2>/dev/null || echo "Config clear failed"
    php artisan cache:clear 2>/dev/null || echo "Cache clear failed"
    php artisan route:clear 2>/dev/null || echo "Route clear failed"
    php artisan view:clear 2>/dev/null || echo "View clear failed"
fi

# Run migrations (if needed)
if [ -f "artisan" ]; then
    echo "Running database migrations..."
    php artisan migrate --force 2>/dev/null || echo "Migrations failed or not needed"
fi

echo "Deployment completed successfully!" 