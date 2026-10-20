@php
    // The fast phone app (PartnerWebAppController). No console underneath: the scripts
    // below draw every screen into <main> and switch between them in place.
    $v = fn (string $path): int => @filemtime(public_path($path)) ?: 1;
    $asset = fn (string $path): string => e(asset($path)) . '?v=' . $v($path);
    $json = fn ($value): string => json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
@endphp
<!DOCTYPE html>
<html lang="en" class="ha-app ha-spa">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>Haraan Partner</title>
    <link rel="manifest" href="{!! $asset('partner.webmanifest') !!}">
    <meta name="theme-color" content="#ffffff">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Haraan Partner">
    <meta name="format-detection" content="telephone=no">
    <link rel="apple-touch-icon" href="{{ asset('partner-app/icon-180.png') }}">
    <link rel="icon" href="{{ asset('partner-app/icon-192.png') }}">
    {!! \App\Support\PartnerSplash::head() !!}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap">
    <link rel="stylesheet" href="{!! $asset('css/partner/app-spa.css') !!}">
    <link rel="stylesheet" href="{!! $asset('css/partner/app-shell.css') !!}">
    <link rel="stylesheet" href="{!! $asset('css/partner/app-screens.css') !!}">
    <link rel="stylesheet" href="{!! $asset('css/partner/app-tools.css') !!}">
    <link rel="stylesheet" href="{!! $asset('css/partner/app-venue-tools.css') !!}">
    <link rel="stylesheet" href="{!! $asset('css/partner/app-bookings.css') !!}">
    <link rel="stylesheet" href="{!! $asset('css/partner/pwa.css') !!}">
    <script>window.HaraanPartnerApp={!! $json($config) !!};window.HaraanPartnerPwa={!! $json(\App\Support\PartnerAppShell::pwaConfig()) !!};</script>
    <script src="{!! $asset('js/partner/app-shell.js') !!}" defer></script>
    <script src="{!! $asset('js/partner/app-screens.js') !!}" defer></script>
    <script src="{!! $asset('js/partner/app-tools.js') !!}" defer></script>
    <script src="{!! $asset('js/partner/app-venue-tools.js') !!}" defer></script>
    <script src="{!! $asset('js/partner/app-bookings.js') !!}" defer></script>
    <script src="{!! $asset('js/partner/pwa.js') !!}" defer></script>
</head>
<body class="ha-spa-body">
    {!! \App\Support\PartnerSplash::body() !!}
    {{-- The screens are drawn into .fi-main: the same host the console version uses. --}}
    <div class="fi-main-ctn"><main class="fi-main" id="ha-main"></main></div>
    <noscript><p style="padding:24px;font-family:system-ui">Haraan Partner needs JavaScript. <a href="/partner">Open the console</a>.</p></noscript>
</body>
</html>
