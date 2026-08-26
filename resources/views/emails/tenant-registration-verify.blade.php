<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Verifikasi Email SIFOBI</title>
</head>
<body style="margin:0;padding:0;background:#F8FAFC;font-family:Arial,Helvetica,sans-serif;color:#0F172A;">
<div style="max-width:480px;margin:40px auto;background:#FFFFFF;border-radius:16px;overflow:hidden;border:1px solid #E2E8F0;">
    <div style="background:#1B4332;padding:28px 32px;">
        <h1 style="margin:0;font-size:20px;color:#FFFFFF;">SIFOBI</h1>
    </div>
    <div style="padding:32px;">
        <h2 style="margin:0 0 12px;font-size:18px;">Halo, {{ $user->name }}!</h2>
        <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#475569;">
            Terima kasih sudah mendaftar. Klik tombol di bawah untuk verifikasi email
            dan mulai masa trial 14 hari Anda.
        </p>
        <p style="text-align:center;margin:28px 0;">
            <a href="{{ $verifyUrl }}"
               style="display:inline-block;background:#1B4332;color:#FFFFFF;text-decoration:none;
                      padding:12px 28px;border-radius:10px;font-size:14px;font-weight:600;">
                Verifikasi Email Saya
            </a>
        </p>
        <p style="margin:0;font-size:12px;color:#94A3B8;">
            Kalau tombol tidak berfungsi, salin tautan ini ke browser Anda:<br>
            <span style="word-break:break-all;">{{ $verifyUrl }}</span>
        </p>
    </div>
</div>
</body>
</html>
