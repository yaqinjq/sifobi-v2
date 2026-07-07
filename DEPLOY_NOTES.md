# SIFOBI v2 Deployment Notes

- Versi: v1.0.0-mvp
- Tanggal: 2026-07-07
- PHP required: 8.4
- Node required: hanya untuk build local, tidak perlu di server production
- Queue: sync driver untuk MVP
- Storage: local/public disk untuk MVP, belum menggunakan S3

## PHP Extensions Required

- pdo_mysql
- mbstring
- openssl
- tokenizer
- xml
- ctype
- json
- bcmath
- gd
- fileinfo
- exif
- zip

## Production Notes

- Jalankan artisan di VPS dengan `/www/server/php/84/bin/php artisan`.
- Jangan upload `.env`, `vendor/`, `node_modules/`, atau file log.
- `public/build/` wajib ikut upload jika server tidak menjalankan Node/Vite.
- Ganti password akun seed production segera setelah login pertama.
- Password akun awal production dibaca dari `.env`, bukan disimpan hardcode di kode.
