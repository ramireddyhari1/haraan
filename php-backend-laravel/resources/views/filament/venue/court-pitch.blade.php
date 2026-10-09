{{-- Top-down plan of a court, drawn for its sport (or kind, for tables/stations/rooms/lanes). $sport, $kind --}}
@php
    $k = in_array($kind, ['table', 'station', 'room', 'lane'], true) ? $kind : strtolower((string) $sport);
    $ink = 'var(--cc-ink)';
    $turf = 'var(--cc-blue-soft)';
    $line = 'var(--cc-surface)';
@endphp
<svg class="cb-pitch" viewBox="0 0 120 80" fill="none" role="img" aria-label="{{ $sport ?: ucfirst($kind) }} court">
    @switch($k)
        @case('cricket')
            <ellipse cx="60" cy="40" rx="56" ry="36" fill="{{ $turf }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <ellipse cx="60" cy="40" rx="30" ry="22" stroke="{{ $ink }}" stroke-width="1" stroke-dasharray="2 3" opacity=".55"/>
            <rect x="56" y="27" width="8" height="26" rx="1" fill="#e9dcc0" stroke="{{ $ink }}" stroke-width="1"/>
            <path d="M54 31h12M54 49h12" stroke="{{ $ink }}" stroke-width="1"/>
            <path d="M58.5 28.5v2M60 28.5v2M61.5 28.5v2M58.5 49.5v2M60 49.5v2M61.5 49.5v2" stroke="{{ $ink }}" stroke-width=".9"/>
            @break
        @case('football')
            <rect x="4" y="6" width="112" height="68" rx="3" fill="{{ $turf }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="M60 6v68" stroke="{{ $ink }}" stroke-width="1"/>
            <circle cx="60" cy="40" r="10" stroke="{{ $ink }}" stroke-width="1"/>
            <circle cx="60" cy="40" r="1.4" fill="{{ $ink }}"/>
            <path d="M4 24h16v32H4M116 24h-16v32h16M4 32h6v16H4M116 32h-6v16h6" stroke="{{ $ink }}" stroke-width="1"/>
            <path d="M20 33a8 8 0 0 1 0 14M100 33a8 8 0 0 0 0 14" stroke="{{ $ink }}" stroke-width="1"/>
            @break
        @case('badminton')
            <rect x="10" y="8" width="100" height="64" rx="2" fill="{{ $turf }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="M10 13h100M10 67h100M15 8v64M105 8v64" stroke="{{ $ink }}" stroke-width=".9"/>
            <path d="M46 8v64M74 8v64" stroke="{{ $ink }}" stroke-width=".9"/>
            <path d="M15 40h31M74 40h31" stroke="{{ $ink }}" stroke-width=".9"/>
            <path d="M60 4v72" stroke="{{ $ink }}" stroke-width="2.2"/>
            @break
        @case('pickleball')
            {{-- 20 × 44 ft court on a wider apron; the 7 ft non-volley "kitchen" each side of the net. --}}
            <rect x="3" y="7" width="114" height="66" rx="4" fill="{{ $ink }}" fill-opacity=".07" stroke="{{ $ink }}" stroke-width="1"/>
            <rect x="10" y="17" width="100" height="46" rx="1" fill="{{ $turf }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <rect x="44" y="17" width="32" height="46" fill="{{ $ink }}" fill-opacity=".12"/>
            <path d="M44 17v46M76 17v46" stroke="{{ $ink }}" stroke-width="1"/>
            <path d="M10 40h34M76 40h34" stroke="{{ $ink }}" stroke-width=".9"/>
            <path d="M60 12v56" stroke="{{ $ink }}" stroke-width="2.2"/>
            <circle cx="60" cy="12" r="1.8" fill="{{ $ink }}"/><circle cx="60" cy="68" r="1.8" fill="{{ $ink }}"/>
            <circle cx="92" cy="29" r="2.4" fill="#e7d33c" stroke="{{ $ink }}" stroke-width=".7"/>
            <path d="M91 28.2h.01M93 29.6h.01M91.6 30.4h.01" stroke="{{ $ink }}" stroke-width=".9" stroke-linecap="round"/>
            @break
        @case('basketball')
            <rect x="4" y="6" width="112" height="68" rx="3" fill="#f4e6d3" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="M60 6v68" stroke="{{ $ink }}" stroke-width="1"/>
            <circle cx="60" cy="40" r="9" stroke="{{ $ink }}" stroke-width="1"/>
            <path d="M4 31h22v18H4M116 31H94v18h22" stroke="{{ $ink }}" stroke-width="1"/>
            <path d="M26 31a9 9 0 0 1 0 18M94 31a9 9 0 0 0 0 18" stroke="{{ $ink }}" stroke-width="1"/>
            <path d="M4 14c34 2 34 50 0 52M116 14c-34 2-34 50 0 52" stroke="{{ $ink }}" stroke-width="1"/>
            @break
        @case('tennis')
            <rect x="6" y="8" width="108" height="64" rx="2" fill="{{ $turf }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="M6 16h108M6 64h108" stroke="{{ $ink }}" stroke-width=".9"/>
            <path d="M33 16v48M87 16v48M33 40h54" stroke="{{ $ink }}" stroke-width=".9"/>
            <path d="M60 4v72" stroke="{{ $ink }}" stroke-width="2.2"/>
            @break
        @case('volleyball')
            <rect x="10" y="10" width="100" height="60" rx="2" fill="#f4e6d3" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="M43 10v60M77 10v60" stroke="{{ $ink }}" stroke-width=".9"/>
            <path d="M60 5v70" stroke="{{ $ink }}" stroke-width="2.2"/>
            <circle cx="60" cy="5" r="1.8" fill="{{ $ink }}"/><circle cx="60" cy="75" r="1.8" fill="{{ $ink }}"/>
            @break
        @case('table')
            <rect x="30" y="20" width="60" height="40" rx="8" fill="{{ $turf }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            @foreach ([[44, 12], [76, 12], [44, 68], [76, 68]] as [$x, $y])
                <rect x="{{ $x - 7 }}" y="{{ $y - 4 }}" width="14" height="8" rx="3" fill="{{ $line }}" stroke="{{ $ink }}" stroke-width="1.1"/>
            @endforeach
            @break
        @case('station')
            <rect x="22" y="14" width="76" height="40" rx="4" fill="{{ $turf }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="M52 54l-4 12h24l-4-12" stroke="{{ $ink }}" stroke-width="1.2" fill="{{ $line }}"/>
            <path d="M44 66h32" stroke="{{ $ink }}" stroke-width="1.4" stroke-linecap="round"/>
            @break
        @case('lane')
            <rect x="4" y="12" width="112" height="56" rx="3" fill="{{ $turf }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="M4 26h112M4 40h112M4 54h112" stroke="{{ $ink }}" stroke-width=".9" stroke-dasharray="5 4"/>
            @break
        @default
            <rect x="6" y="8" width="108" height="64" rx="6" fill="{{ $turf }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="M60 8v64" stroke="{{ $ink }}" stroke-width="1" stroke-dasharray="3 4"/>
    @endswitch
</svg>
