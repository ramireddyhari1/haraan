{{-- Shown by EnforcePlatformOperations while /control → Operations → Maintenance mode is on.
     Standalone on purpose: no layout, no queries, nothing that could fail mid-maintenance.
     The scene is the app's PitchRollerArt (maintenance/PitchRollerArt.kt), same 320×180 box
     and the same points, so web and app show one drawing. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Back shortly · Haraan</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px;
            font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #F8FAFC; color: #0F172A;
        }
        main { width: 100%; max-width: 440px; text-align: center; }
        .mark { display: block; height: 36px; width: auto; margin: 0 auto 16px; }
        .scene { display: block; width: 100%; height: auto; margin: 0 auto 24px; }
        h1 { font-size: 24px; line-height: 1.25; margin: 0 0 10px; letter-spacing: -0.01em; }
        p { margin: 0; font-size: 16px; line-height: 1.55; color: #475569; }
        .retry {
            margin-top: 28px; height: 44px; padding: 0 22px; border-radius: 12px;
            border: 1px solid #CBD5E1; background: transparent; color: #0F172A;
            font-family: inherit; font-size: 15px; font-weight: 600; cursor: pointer;
        }
        .retry:hover { border-color: #94A3B8; }
        .note { margin-top: 12px; font-size: 12px; color: #94A3B8; }

        /* The roller goes up and down the strip; the bolts turn by distance / radius
           (114 units of travel on a 17-unit drum ≈ 384°), on the same timing so it rolls. */
        .roller { animation: roll 7s ease-in-out infinite alternate; }
        .bolts { transform-box: fill-box; transform-origin: center; animation: spin 7s ease-in-out infinite alternate; }
        @keyframes roll { from { transform: translateX(-57px); } to { transform: translateX(57px); } }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(384deg); } }
        @media (prefers-reduced-motion: reduce) { .roller, .bolts { animation: none; } }
    </style>
</head>
<body>
<main>
    <img class="mark" src="/images/haraan-logo-blue.png" alt="Haraan">

    <svg class="scene" viewBox="0 0 320 180" role="img" aria-label="A roller working a cricket pitch">
        <defs>
            <clipPath id="top"><polygon points="10,134 286,134 310,104 34,104"/></clipPath>
        </defs>

        {{-- Outfield and the strip's shadow --}}
        <ellipse cx="160" cy="127" rx="156" ry="35" fill="#E7F3E4"/>
        <ellipse cx="166" cy="148" rx="148" ry="8" fill="#0F172A" opacity=".07"/>

        {{-- Strip: right side, front edge (grass, soil), top face --}}
        <polygon points="286,134 310,104 310,111 286,141" fill="#5B9455"/>
        <polygon points="286,141 310,111 310,117 286,147" fill="#B08B66"/>
        <rect x="10" y="134" width="276" height="7" fill="#6DA866"/>
        <rect x="10" y="141" width="276" height="6" fill="#C7A27C"/>
        <polygon points="10,134 286,134 310,104 34,104" fill="#A8D8A0"/>
        <g clip-path="url(#top)" fill="#96CD8F">
            @for ($x = 33; $x < 286; $x += 46)
                <polygon points="{{ $x }},134 {{ $x + 23 }},134 {{ $x + 47 }},104 {{ $x + 24 }},104"/>
            @endfor
        </g>

        {{-- Creases --}}
        <g stroke="#fff" stroke-opacity=".95" stroke-width="1.6" stroke-linecap="round">
            <line x1="42" y1="133" x2="65" y2="104.2"/>
            <line x1="254" y1="133" x2="277" y2="104.2"/>
        </g>

        {{-- Far end: stumps standing, bails on --}}
        <g stroke-linecap="round">
            <g stroke="#0F172A" stroke-opacity=".1" stroke-width="2.4">
                <line x1="261.2" y1="125" x2="270.2" y2="127"/>
                <line x1="266" y1="119" x2="275" y2="121"/>
                <line x1="270.8" y1="113" x2="279.8" y2="115"/>
            </g>
            <g stroke="#E0BC86" stroke-width="2.1">
                <line x1="261.2" y1="125" x2="261.2" y2="101"/>
                <line x1="266" y1="119" x2="266" y2="95"/>
                <line x1="270.8" y1="113" x2="270.8" y2="89"/>
            </g>
            <g stroke="#B4884F" stroke-width="1.8">
                <line x1="261.2" y1="100" x2="266" y2="94"/>
                <line x1="266" y1="94" x2="270.8" y2="88"/>
            </g>
        </g>

        {{-- Near end: stumps pulled and laid on the grass --}}
        <g stroke-linecap="round">
            <g stroke="#B4884F" stroke-opacity=".35" stroke-width="2.4">
                <line x1="19" y1="130.6" x2="41" y2="130.6"/>
                <line x1="21.5" y1="127.2" x2="43.5" y2="127.2"/>
                <line x1="24" y1="123.8" x2="46" y2="123.8"/>
            </g>
            <g stroke="#E0BC86" stroke-width="2.6">
                <line x1="18" y1="129" x2="40" y2="129"/>
                <line x1="20.5" y1="125.6" x2="42.5" y2="125.6"/>
                <line x1="23" y1="122.2" x2="45" y2="122.2"/>
            </g>
        </g>

        {{-- The roller, drawn at mid-strip and slid by CSS --}}
        <g class="roller">
            <ellipse cx="160.6" cy="129.7" rx="15" ry="3" fill="#0F172A" opacity=".16"/>
            <line x1="170.4" y1="90.3" x2="118.5" y2="52.6" stroke="#334155" stroke-width="3" stroke-linecap="round"/>
            <circle cx="170.4" cy="90.3" r="17" fill="#1E3A8A"/>
            <polygon points="164.9,124.3 183.6,100.9 157.1,79.7 138.4,103.1" fill="#1D4ED8"/>
            <circle cx="151.6" cy="113.7" r="17" fill="#2563EB" stroke="#1E40AF" stroke-width="1.6"/>
            <line x1="142.1" y1="106.1" x2="160.8" y2="82.7" stroke="#fff" stroke-opacity=".28" stroke-width="2.2" stroke-linecap="round"/>
            <circle cx="151.6" cy="113.7" r="12" fill="none" stroke="#fff" stroke-opacity=".22" stroke-width="1.2"/>
            <g class="bolts" fill="#fff" fill-opacity=".9">
                {{-- An invisible ring keeps the rotation centred on the hub. --}}
                <circle cx="151.6" cy="113.7" r="13.5" fill="none"/>
                <circle cx="163.6" cy="113.7" r="1.5"/>
                <circle cx="145.6" cy="124.1" r="1.5"/>
                <circle cx="145.6" cy="103.3" r="1.5"/>
            </g>
            <circle cx="151.6" cy="113.7" r="3.2" fill="#0F172A"/>
            <circle cx="151.6" cy="113.7" r="1.2" fill="#fff" fill-opacity=".9"/>
            <line x1="151.6" y1="113.7" x2="101.6" y2="73.7" stroke="#334155" stroke-width="3.2" stroke-linecap="round"/>
            <line x1="99.4" y1="76.5" x2="120.7" y2="49.8" stroke="#0F172A" stroke-width="4.2" stroke-linecap="round"/>
        </g>
    </svg>

    <h1>{{ ($title ?? '') !== '' ? $title : 'We’re making Haraan better' }}</h1>
    <p>{{ $message }}</p>
    <button class="retry" type="button" onclick="location.reload()">Try again</button>
    <div class="note">This page opens by itself once we’re back.</div>
</main>
<script>
    // Look again every 45s; the moment maintenance is off, the real page loads.
    setInterval(function () {
        fetch(location.href, { method: 'HEAD', cache: 'no-store' })
            .then(function (r) { if (r.status !== 503) location.reload(); })
            .catch(function () {});
    }, 45000);
</script>
</body>
</html>
