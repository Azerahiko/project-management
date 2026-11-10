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

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <p style="margin: 0 0 12px; font-weight: 600; color: #1e40af;">Password Sementara Anda:</p>
            <div style="background: #ffffff; border: 2px solid #3b82f6; border-radius: 6px; padding: 15px; text-align: center; font-family: monospace; font-size: 18px; font-weight: 600; letter-spacing: 2px; color: #1e40af; word-break: break-all;">
                {{ $temporaryPassword }}
            </div>
            <p style="margin: 12px 0 0; font-size: 13px; color: #475569;">
                Password ini hanya berlaku untuk login pertama Anda. Anda harus membuat password baru saat proses onboarding.<br>
                Password ini <strong>tidak</strong> boleh dibagikan kepada siapa pun.
            </p>
        </div>

        <div style="background: #fef2f2; border: 1px solid #fee2e2; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
            <p style="margin: 0 0 8px; font-weight: 600; color: #991b1b;">Peringatan Keamanan:</p>
            <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #7f1d1d;">
                <li style="margin-bottom: 4px;">Password sementara ini hanya untuk penggunaan sementara</li>
                <li style="margin-bottom: 4px;">Anda harus mengubahnya segera setelah login pertama</li>
                <li style="margin-bottom: 4px;">Jangan simpan password ini di tempat yang tidak aman</li>
                <li style="margin-bottom: 4px;">Jangan bagikan email atau password ini kepada siapa pun</li>
            </ul>
        </div>

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