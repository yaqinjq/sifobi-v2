#!/bin/bash
# SIFOBI v2 Deploy Script
# Jalankan di server via SSH setelah upload files.

set -e

PHP=/www/server/php/84/bin/php
ARTISAN="$PHP artisan"

echo "=== SIFOBI v2 Deploy ==="

echo "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "Clearing caches..."
$ARTISAN config:clear
$ARTISAN route:clear
$ARTISAN view:clear
$ARTISAN cache:clear
$ARTISAN event:clear

echo "Running migrations..."
$ARTISAN migrate --force

echo "Optimizing..."
$ARTISAN config:cache
$ARTISAN route:cache
$ARTISAN view:cache
$ARTISAN event:cache

echo "Creating storage link..."
$ARTISAN storage:link

echo "Setting permissions..."
chmod -R 755 storage bootstrap/cache
chown -R www:www storage bootstrap/cache

echo "=== Deploy selesai ==="
$ARTISAN --version
