#!/bin/bash
set -e

APP_DIR="/home/u838520432/domains/memoshot.com.au/laravel-app"
PUBLIC_DIR="/home/u838520432/domains/memoshot.com.au/public_html"

cd "$APP_DIR"

composer install --no-dev --optimize-autoloader

if [ -f package.json ]; then
    npm ci
    npm run build
fi

rsync -a --delete "$APP_DIR/public/" "$PUBLIC_DIR/"

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
