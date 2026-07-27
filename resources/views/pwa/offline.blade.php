<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1B4332">
    <title>Tidak Ada Koneksi</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; font-family: system-ui, -apple-system, sans-serif; background: #f9fafb; color: #1f2937; }
        .container { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; text-align: center; }
        .icon { width: 80px; height: 80px; background: #1B4332; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; }
        .icon svg { width: 40px; height: 40px; color: white; stroke: white; fill: none; }
        h1 { font-size: 1.5rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem; }
        p  { font-size: 0.9375rem; color: #6b7280; margin-bottom: 2rem; max-width: 320px; line-height: 1.6; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; background: #1B4332; color: white; font-size: 0.9375rem; font-weight: 600; padding: 0.75rem 1.5rem; border-radius: 12px; border: none; cursor: pointer; text-decoration: none; }
        .btn:active { opacity: 0.85; }
        .badge { margin-top: 2rem; font-size: 0.75rem; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 3l18 18M10.584 10.587a2 2 0 002.828 2.83M9.363 5.365A9 9 0 0119.07 14.783M6.343 17.657A9 9 0 015.07 15M12 3c-1.2 0-2.4.2-3.5.57"/>
            </svg>
        </div>
        <h1>Anda Sedang Offline</h1>
        <p>Periksa koneksi internet Anda, lalu coba lagi. Data inventori memerlukan koneksi untuk ditampilkan.</p>
        <button class="btn" onclick="window.location.reload()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 12a9 9 0 109-9 9.75 9.75 0 00-6.74 2.74L3 8"/>
                <path d="M3 3v5h5"/>
            </svg>
            Coba Lagi
        </button>
        <p class="badge">SIFOBI &mdash; Inventory System</p>
    </div>
</body>
</html>
