# Panduan Deploy SIFOBI v2 ke aaPanel

Target:

- Domain: `sifobi.mykopiogroup.com`
- Path: `/www/wwwroot/sifobi`
- PHP: `/www/server/php/84/bin/php`
- Database: `database_sifobi_prod`

## A. Persiapan di aaPanel

Lakukan sekali saja.

1. Login ke aaPanel.
2. Buat Website baru:
   - Domain: `sifobi.mykopiogroup.com`
   - Root path: `/www/wwwroot/sifobi`
   - PHP version: 8.4
3. Set Document Root ke subfolder `public/`:
   - aaPanel -> Website -> Settings -> Running Directory
   - Ubah ke: `/www/wwwroot/sifobi/public`
4. Aktifkan SSL:
   - aaPanel -> Website -> SSL -> Let's Encrypt
   - Aktifkan Force HTTPS
5. Buat Database MySQL:
   - aaPanel -> Database -> Add
   - DB Name: `database_sifobi_prod`
   - Username: `sifobi_user`
   - Password: buat password kuat
6. Verifikasi PHP extensions aktif:
   - `pdo_mysql`
   - `mbstring`
   - `gd`
   - `fileinfo`
   - `openssl`
   - `exif`
   - `bcmath`
   - `zip`

## B. Upload Files Pertama Kali

### Cara 1: File Manager aaPanel

1. Zip folder project `sifobi/`.
2. Exclude:
   - `vendor/`
   - `node_modules/`
   - `.env`
   - `storage/logs/*.log`
3. Pastikan `public/build/` ikut masuk zip.
4. Upload zip ke `/www/wwwroot/sifobi/`.
5. Extract di server.
6. Hapus file zip setelah extract.

### Cara 2: Git

```bash
cd /www/wwwroot/
git clone https://github.com/[username]/sifobi.git sifobi
cd /www/wwwroot/sifobi
bash deploy-fresh.sh
```

Update berikutnya:

```bash
cd /www/wwwroot/sifobi
git pull origin main
bash deploy.sh
```

## C. Konfigurasi `.env` di Server

```bash
cd /www/wwwroot/sifobi
cp .env.production.example .env
nano .env
```

Isi nilai berikut:

```dotenv
APP_KEY=
DB_USERNAME=sifobi_user
DB_PASSWORD=[password database]
DB_DATABASE=database_sifobi_prod
SIFOBI_PROD_ADMIN_PASSWORD=[password admin awal yang kuat]
SIFOBI_PROD_STAFF_PASSWORD=[password staff test awal yang kuat]
```

Generate app key dengan:

```bash
/www/server/php/84/bin/php artisan key:generate --show
```

Paste hasilnya ke `APP_KEY=`.

## D. Fresh Install Pertama Kali

Gunakan hanya jika database production masih kosong.

```bash
cd /www/wwwroot/sifobi
bash deploy-fresh.sh
```

Jika tidak memakai SSH, jalankan di Terminal aaPanel:

```bash
cd /www/wwwroot/sifobi
composer install --no-dev --optimize-autoloader --no-interaction
/www/server/php/84/bin/php artisan key:generate --force
/www/server/php/84/bin/php artisan migrate:fresh --force
/www/server/php/84/bin/php artisan db:seed --class=RolesAndPermissionsSeeder --force
/www/server/php/84/bin/php artisan db:seed --class=MinimumMasterDataSeeder --force
/www/server/php/84/bin/php artisan db:seed --class=ItemJenisSeeder --force
/www/server/php/84/bin/php artisan db:seed --class=ItemCategorySeeder --force
/www/server/php/84/bin/php artisan db:seed --class=ProductionAdminSeeder --force
/www/server/php/84/bin/php artisan storage:link
/www/server/php/84/bin/php artisan config:cache
/www/server/php/84/bin/php artisan route:cache
/www/server/php/84/bin/php artisan view:cache
/www/server/php/84/bin/php artisan event:cache
chmod -R 755 storage bootstrap/cache
chown -R www:www storage bootstrap/cache
```

Login awal:

- Admin: `admin@mykopiogroup.com` / nilai `SIFOBI_PROD_ADMIN_PASSWORD`
- Staff test: `staff.bar@mykopiogroup.com` / nilai `SIFOBI_PROD_STAFF_PASSWORD`

Ganti password segera setelah login pertama.

## E. Update Selanjutnya

Dengan Git:

```bash
cd /www/wwwroot/sifobi
git pull origin main
bash deploy.sh
```

Manual upload:

```bash
cd /www/wwwroot/sifobi
/www/server/php/84/bin/php artisan down
[upload files baru]
composer install --no-dev --optimize-autoloader --no-interaction
/www/server/php/84/bin/php artisan migrate --force
/www/server/php/84/bin/php artisan config:clear
/www/server/php/84/bin/php artisan route:clear
/www/server/php/84/bin/php artisan view:clear
/www/server/php/84/bin/php artisan cache:clear
/www/server/php/84/bin/php artisan config:cache
/www/server/php/84/bin/php artisan route:cache
/www/server/php/84/bin/php artisan view:cache
/www/server/php/84/bin/php artisan event:cache
/www/server/php/84/bin/php artisan up
```

## F. Nginx Config

aaPanel biasanya membuat config otomatis. Verifikasi site config memiliki:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ /\.(env|git) {
    deny all;
}
```

Rekomendasi security header di Nginx:

```nginx
add_header X-Frame-Options "SAMEORIGIN";
add_header X-Content-Type-Options "nosniff";
add_header X-XSS-Protection "1; mode=block";
add_header Referrer-Policy "strict-origin-when-cross-origin";
```

## G. Checklist Post-Deploy

- `https://sifobi.mykopiogroup.com` menampilkan halaman login.
- Login admin production berhasil.
- Password admin production diganti.
- User operasional dibuat.
- Settings -> Brands & Outlets diisi data real.
- Settings -> Integration dikonfigurasi jika OMEO/OCIA siap.
- Master Data -> Import item dari template Excel.
- Test Open Stock satu item.
- Pastikan `stock_mutations` dan `stock_balances` terisi setelah posting.
- Cek `storage/logs/laravel-*.log` jika ada error.
