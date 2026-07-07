#!/bin/bash
# HANYA untuk install PERTAMA KALI di server baru.
# JANGAN jalankan ini jika sudah ada data production.

set -e

PHP=/www/server/php/84/bin/php
ARTISAN="$PHP artisan"

echo "=== SIFOBI v2 FRESH INSTALL ==="
echo "WARNING: Ini akan reset database!"
read -p "Ketik 'yes' untuk lanjut: " confirm

if [ "$confirm" != "yes" ]; then
    echo "Dibatalkan."
    exit 1
fi

composer install --no-dev --optimize-autoloader --no-interaction

$ARTISAN key:generate --force
$ARTISAN migrate:fresh --force
$ARTISAN db:seed --class=RolesAndPermissionsSeeder --force
$ARTISAN db:seed --class=MinimumMasterDataSeeder --force
$ARTISAN db:seed --class=ItemJenisSeeder --force
$ARTISAN db:seed --class=ItemCategorySeeder --force
$ARTISAN db:seed --class=ProductionAdminSeeder --force
$ARTISAN storage:link

chmod -R 755 storage bootstrap/cache
chown -R www:www storage bootstrap/cache

$ARTISAN config:cache
$ARTISAN route:cache
$ARTISAN view:cache
$ARTISAN event:cache

echo "=== Fresh install selesai ==="
echo "Login production: admin@mykopiogroup.com / password dari SIFOBI_PROD_ADMIN_PASSWORD"
echo "Login test staff: staff.bar@mykopiogroup.com / password dari SIFOBI_PROD_STAFF_PASSWORD"
echo "GANTI PASSWORD SEGERA!"
