{{-- Shown by EnforcePlatformOperations while /control → Operations → Maintenance mode is on.
     Standalone on purpose: no layout, no queries, nothing that could fail mid-maintenance. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Back shortly · Haraan</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #F8FAFC; color: #0F172A;
        }
        main { max-width: 420px; text-align: center; }
        .mark { font-weight: 800; font-size: 22px; letter-spacing: -0.02em; color: #2563EB; margin-bottom: 28px; }
        h1 { font-size: 24px; line-height: 1.25; margin: 0 0 12px; letter-spacing: -0.01em; }
        p { margin: 0; font-size: 16px; line-height: 1.55; color: #475569; }
        @media (prefers-color-scheme: dark) {
            body { background: #0B1220; color: #E2E8F0; }
            p { color: #94A3B8; }
            .mark { color: #60A5FA; }
        }
    </style>
</head>
<body>
<main>
    <div class="mark">Haraan</div>
    <h1>We’re making Haraan better</h1>
    <p>{{ $message }}</p>
</main>
</body>
</html>
