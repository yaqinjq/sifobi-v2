<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR Meja {{ $table->code }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #f3f4f6; padding: 2rem 0; }
        .card { width: 340px; margin: 0 auto; background: #fff; padding: 1.5rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.15); text-align: center; }
        h1 { font-size: 1.1rem; margin-bottom: 0.25rem; }
        .sub { font-size: 0.8rem; color: #6b7280; margin-bottom: 1rem; }
        img { width: 260px; height: 260px; margin: 0 auto 1rem; display: block; }
        .url { font-size: 0.7rem; color: #9ca3af; word-break: break-all; margin-top: 0.5rem; }
        .actions { width: 340px; margin: 1rem auto 0; text-align: center; }
        .btn { display: inline-block; background: #1B4332; color: #fff; padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-size: 0.875rem; border: none; cursor: pointer; }
        @media print {
            body { background: #fff; padding: 0; }
            .card { box-shadow: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $table->outlet->name ?? '-' }}</h1>
        <p class="sub">Meja {{ $table->code }} &mdash; Scan untuk pesan</p>
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&data={{ urlencode($signedUrl) }}" alt="QR Meja {{ $table->code }}">
        <p class="url">{{ $signedUrl }}</p>
    </div>
    <div class="actions">
        <button class="btn" onclick="window.print()">Cetak</button>
    </div>
</body>
</html>
