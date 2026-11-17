<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undangan Bergabung</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #090c10; background-color: #f6f8fa; margin: 0; padding: 32px 16px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e7ee; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.05);">
        <div style="background-color: #0f131a; padding: 24px 32px; border-bottom: 2px solid #214fe6;">
            <div style="display: inline-block; font-family: monospace; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; color: #8eb8ff; text-transform: uppercase; margin-bottom: 6px;">Workspace Invitation</div>
            <h1 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.02em;">Undangan Bergabung ke {{ $appName }}</h1>
        </div>

        <div style="padding: 32px;">
            <p style="margin-top: 0; font-size: 15px; color: #090c10;">Halo,</p>

            <p style="font-size: 14px; color: #454f5b;">Anda telah diundang untuk bergabung dalam workspace kolaborasi <strong>{{ $appName }}</strong>.</p>

            <p style="font-size: 14px; color: #454f5b; margin-bottom: 28px;">Silakan klik tombol di bawah untuk memverifikasi alamat email dan mengatur kata sandi akun Anda:</p>

            <div style="text-align: left; margin-bottom: 28px;">
                <a href="{{ $invitationUrl }}" style="display: inline-block; background-color: #214fe6; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 600; letter-spacing: -0.01em;">
                    Verifikasi Akun & Atur Password &rarr;
                </a>
            </div>

            <p style="color: #637381; font-size: 12px; margin-bottom: 0;">
                Jika tombol tidak berfungsi, salin dan tempel tautan berikut ke browser:<br>
                <span style="font-family: monospace; font-size: 11px; color: #214fe6; word-break: break-all;">{{ $invitationUrl }}</span>
            </p>

            @if ($temporaryPassword)
            <div style="background: #f6f8fa; border: 1px solid #cbd3df; border-radius: 6px; padding: 18px; margin-top: 24px;">
                <p style="margin: 0 0 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #454f5b;">Password Sementara:</p>
                <div style="background: #ffffff; border: 1px solid #214fe6; border-radius: 4px; padding: 12px; text-align: center; font-family: monospace; font-size: 16px; font-weight: 700; letter-spacing: 2px; color: #193cc0; word-break: break-all;">
                    {{ $temporaryPassword }}
                </div>
                <p style="margin: 8px 0 0; font-size: 12px; color: #637381;">
                    Password ini hanya aktif untuk proses aktivasi akun awal. Segera perbarui setelah berhasil masuk.
                </p>
            </div>
            @endif

            <hr style="border: none; border-top: 1px solid #e2e7ee; margin: 28px 0 20px;">

            <p style="color: #919eab; font-size: 12px; margin: 0; line-height: 1.5;">
                Tautan ini berlaku selama {{ config('activation.expiry_hours', 72) }} jam. Jika Anda merasa tidak meminta akses ini, abaikan email ini dengan aman.
            </p>
        </div>
    </div>

    <div style="text-align: center; color: #919eab; font-size: 11px; margin-top: 20px; font-family: monospace;">
        &copy; {{ date('Y') }} {{ $appName }} &bull; Workspace Management
    </div>
</body>
</html>