<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name', 'Dijital Code') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/images/logo-mark.png" type="image/png">
    <style>
        *, ::after, ::before { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }
        .error-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 2.5rem;
            max-width: 28rem;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }
        .code {
            display: inline-block;
            font-size: 0.875rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            background-color: #eff6ff;
            color: #2563eb;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.75rem;
        }
        p {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #ffffff;
            background-color: #0f5132;
            border-radius: 0.75rem;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #0a3622;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <span class="code">@yield('code')</span>
        <h1>@yield('header')</h1>
        <p>@yield('message')</p>
        <a href="/" class="btn">Kembali ke Beranda</a>
    </div>
</body>
</html>
