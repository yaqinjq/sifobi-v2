# Panduan Deploy di 2 Server dari 1 GitHub Repo

## Konsep

Satu GitHub repository private dapat dipakai untuk dua deployment yang berbeda.

```text
1 GitHub Repository
        |
        |-- Server A: MKO Group Internal
        |   Domain: sifobi.mykopiogroup.com
        |   .env:
        |     APP_NAME="SIFOBI MKO Group"
        |     OCIA_BASE_URL=https://ocia.mykopiogroup.com
        |     OCIA_API_TOKEN=token_internal_mko
        |
        |-- Server B: Produk SaaS Mandiri
            Domain: app.namaprodukanda.com
            .env:
              APP_NAME="NamaProdukAnda"
              PAYLABS_MERCHANT_ID=xxx
              PAYLABS_API_KEY=xxx
```

## Yang Sama di Kedua Server

- Kode aplikasi dari repository GitHub yang sama.
- Versi PHP, Laravel, dependency Composer, dan database schema.
- Script deployment `deploy.sh`.
- Proses update: `git pull`, `composer install`, `php artisan migrate --force`, lalu cache production.

## Yang Berbeda di Kedua Server

- File `.env`, termasuk nama app, domain, kredensial database, dan API key.
- Database dan data operasional masing-masing server.
- Logo, favicon, dan nama aplikasi yang bisa diubah lewat `/settings/app`.
- Konfigurasi integrasi yang bisa diatur lewat `/settings/integrations`.

## Cara Update Kedua Server Setelah Push ke GitHub

```bash
git push origin main
```

Server A, MKO Group:

```bash
ssh user@server-a
cd /www/wwwroot/sifobi
git pull origin main
bash deploy.sh
```

Server B, produk SaaS:

```bash
ssh user@server-b
cd /www/wwwroot/sifobi
git pull origin main
bash deploy.sh
```

## Contoh `.env` Server A: MKO Group

```env
APP_NAME="SIFOBI MKO Group"
APP_URL=https://sifobi.mykopiogroup.com
DB_DATABASE=database_sifobi_mko
OCIA_BASE_URL=https://ocia.mykopiogroup.com
OCIA_API_TOKEN=token_dari_omeo
```

## Contoh `.env` Server B: Produk SaaS

```env
APP_NAME="NamaProdukAnda"
APP_URL=https://app.namaproduk.com
DB_DATABASE=database_sifobi_saas
PAYLABS_MERCHANT_ID=merchant_id_dari_paylabs
PAYLABS_API_KEY=api_key_dari_paylabs
PAYLABS_SECRET_KEY=secret_key_dari_paylabs
```

Server SaaS klien lain tidak perlu mengisi OCIA bila tidak memakai integrasi OMEO/MKO.

## Catatan Branding

Branding default dapat diatur dari `.env` untuk fresh install. Setelah aplikasi berjalan, ubah logo, favicon, nama aplikasi, dan kontak dari menu:

```text
Pengaturan -> Tampilan Aplikasi
```

Perubahan branding tersimpan di database masing-masing server, sehingga aman walaupun kode berasal dari repository yang sama.
