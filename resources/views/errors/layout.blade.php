<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name', 'DigitalCode') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/images/logo-mark.png" type="image/png">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        *, ::after, ::before { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f6f8fa;
            color: #090c10;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }
        .error-card {
            background: #ffffff;
            border: 1px solid #e2e7ee;
            border-radius: 0.75rem;
            padding: 2.5rem;
            max-width: 30rem;
            width: 100%;
            text-align: left;
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.05);
        }
        .code {
            display: inline-block;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            background-color: #eef4ff;
            color: #214fe6;
            border: 1px solid #bcd3ff;
            margin-bottom: 1.25rem;
        }
        h1 {
            font-size: 1.375rem;
            font-weight: 700;
            color: #090c10;
            margin-bottom: 0.75rem;
            letter-spacing: -0.02em;
        }
        p {
            font-size: 0.875rem;
            color: #57606a;
            line-height: 1.6;
            margin-bottom: 1.75rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #ffffff;
            background-color: #214fe6;
            border-radius: 0.375rem;
            text-decoration: none;
            transition: background-color 0.15s ease;
        }
        .btn:hover {
            background-color: #193cc0;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <span class="code">@yield('code')</span>
        <h1>@yield('header')</h1>
        <p>@yield('message')</p>
        <a href="/" class="btn">Kembali ke Beranda &rarr;</a>
    </div>
</body>
</html>