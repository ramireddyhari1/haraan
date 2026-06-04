<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Haraan Partner' }}</title>
    <style>
        :root {
            --bg: #f8f1ea;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --brand: #8b1e3f;
            --brand-2: #c48a1e;
            --brand-soft: #fff4e7;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: radial-gradient(circle at top left, #fff7ed 0%, var(--bg) 42%, #fdf2f8 100%); color: var(--text); }
        .app { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .sidebar { background: linear-gradient(180deg, #fffdf9 0%, #fff7f3 100%); border-right: 1px solid var(--line); padding: 18px; }
        .logo-wrap { display: grid; place-items: center; margin-bottom: 18px; }
        .brand { font-size: 20px; font-weight: 900; color: var(--brand); letter-spacing: 0.02em; }
        .section-label { margin: 16px 10px 6px; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; }
        .nav a { display: block; color: #334155; text-decoration: none; padding: 10px 12px; margin: 4px 0; border-radius: 10px; font-size: 14px; font-weight: 600; }
        .nav a.active, .nav a:hover { background: linear-gradient(90deg, var(--brand), var(--brand-2)); color: #fff; }
        .main { padding: 18px; }
        .topbar { background: rgba(255,255,255,0.82); border: 1px solid rgba(194, 132, 30, 0.18); backdrop-filter: blur(10px); border-radius: 14px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .topbar h1 { margin: 0; font-size: 20px; }
        .pill { background: #fffaf4; border: 1px solid rgba(139, 30, 63, 0.12); border-radius: 10px; padding: 8px 10px; font-size: 12px; color: #7c2d12; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 18px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .metric .label { color: var(--muted); font-size: 12px; font-weight: 600; }
        .metric .value { font-size: 20px; margin-top: 6px; font-weight: 800; }
        .metric .note, .subtle { color: #64748b; font-size: 13px; margin-top: 6px; }
        .placeholder { color: #475569; font-size: 14px; background: var(--brand-soft); border: 1px solid #f0d7bc; border-radius: 12px; padding: 12px; }
        .hero { display: grid; gap: 10px; margin-bottom: 14px; }
        .eyebrow { color: var(--brand); text-transform: uppercase; letter-spacing: 0.14em; font-size: 11px; font-weight: 800; }
        .hero h2 { margin: 0; font-size: 24px; }
        .hero p { margin: 0; color: var(--muted); max-width: 850px; line-height: 1.6; }
        .stack { display: grid; gap: 12px; }
        .list { display: grid; gap: 10px; }
        .list-item { padding: 14px; border-radius: 14px; border: 1px solid var(--line); background: #fffdf9; }
        .list-item strong { display: block; margin-bottom: 4px; }
        .list-item span { color: var(--muted); font-size: 13px; }
        .action { display: inline-flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 10px; background: linear-gradient(90deg, var(--brand), var(--brand-2)); color: #fff; text-decoration: none; font-weight: 700; }
        .login-shell { min-height: 100vh; display: grid; place-items: center; background: radial-gradient(circle at 20% 10%, #fff1e7 0%, #f8f1ea 45%, #fcf6fb 100%); }
        .login-card { width: min(440px, 92vw); background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 24px; }
        .login-card h1 { margin: 0 0 12px; }
        .field { display: grid; gap: 6px; margin-bottom: 12px; }
        .field input { border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; }
        .btn { border: 0; border-radius: 10px; background: linear-gradient(90deg, var(--brand), var(--brand-2)); color: #fff; padding: 10px 12px; font-weight: 700; cursor: pointer; }
        @media (max-width: 980px) {
            .app { grid-template-columns: 1fr; }
            .sidebar { border-right: 0; border-bottom: 1px solid var(--line); }
            .grid-3, .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@if(request()->routeIs('partner.login'))
    @yield('content')
@else
    <div class="app">
        <aside class="sidebar">
            <div class="logo-wrap">
                <div class="brand">Book &amp; Vibe Partner</div>
            </div>
            <nav class="nav">
                <div class="section-label">Portal</div>
                <a class="{{ request()->routeIs('partner.dashboard') ? 'active' : '' }}" href="{{ route('partner.dashboard') }}">Dashboard</a>
                <a class="{{ request()->routeIs('partner.login') ? 'active' : '' }}" href="{{ route('partner.login') }}">Login</a>

                <div class="section-label">Operations</div>
                <a class="{{ request()->routeIs('partner.events') ? 'active' : '' }}" href="{{ route('partner.events') }}">Events</a>
                <a class="{{ request()->routeIs('partner.gamehub') ? 'active' : '' }}" href="{{ route('partner.gamehub') }}">GameHub</a>
                <a class="{{ request()->routeIs('partner.workers') ? 'active' : '' }}" href="{{ route('partner.workers') }}">Workers</a>

                <div class="section-label">Exit</div>
                <a href="{{ route('portal.index') }}">Back to Portal</a>
            </nav>
        </aside>

        <main class="main">
            <header class="topbar">
                <h1>{{ $title ?? 'Partner Dashboard' }}</h1>
                <div class="pill">{{ now()->format('D, M d, Y') }}</div>
            </header>
            @yield('content')
        </main>
    </div>
@endif
</body>
</html>