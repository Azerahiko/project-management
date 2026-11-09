<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undangan Bergabung</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1f2937; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); padding: 30px; border-radius: 12px 12px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Undangan Bergabung ke {{ $appName }}</h1>
    </div>

    <div style="background: #ffffff; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 12px 12px;">
        <p style="margin-top: 0;">Halo,</p>

        <p>Anda telah diundang untuk bergabung sebagai anggota di <strong>{{ $appName }}</strong>.</p>

        <p style="margin-bottom: 30px;">Silakan klik tombol di bawah untuk memverifikasi alamat email Anda:</p>

        <div style="text-align: center; margin-bottom: 30px;">
            <a href="{{ $invitationUrl }}" style="display: inline-block; background: #3b82f6; color: white; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: 600;">
                Verifikasi Email
            </a>
        </div>

        <p style="color: #6b7280; font-size: 14px; margin-bottom: 0;">
            Jika tombol tidak berfungsi, salin dan tempel tautan berikut ke browser Anda:<br>
            <span style="word-break: break-all;">{{ $invitationUrl }}</span>
        </p>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">

        <p style="color: #9ca3af; font-size: 12px; margin: 0;">
            Tautan ini akan kedaluwarsa dalam {{ config('activation.expiry_hours', 72) }} jam.
            Jika Anda tidak meminta undangan ini, abaikan email ini.
        </p>
    </div>

    <p style="text-align: center; color: #9ca3af; font-size: 12px; margin-top: 20px;">
        &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
    </p>
</body>
</html>