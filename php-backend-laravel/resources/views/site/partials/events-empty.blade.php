{{-- Empty Events page: an empty stage. The curtains part, the spot comes on over a lone
     mic, and a ticket carrying the H drops in — the room is ready, the show just isn't
     listed yet. 1:1 with the app's EventsNoShows (MainScreen.kt); change both together.

     Included twice on /events (phone .mhome and desktop grid both sit in the DOM), so
     gradient ids are suffixed with $uid. One entrance, never looping; reduced motion
     gets the finished frame. --}}
@php
    $uid = $uid ?? 'a';
    $evxCat = $category ?? null;
    $evxCat = ($evxCat && strcasecmp($evxCat, 'All') !== 0) ? $evxCat : null;
@endphp
<div class="evx" role="status">
    <svg class="evx__scene" viewBox="0 0 360 176" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="evxWall{{ $uid }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#15296B"/><stop offset="1" stop-color="#1E3A8A"/></linearGradient>
            <linearGradient id="evxFloor{{ $uid }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2B4FB8"/><stop offset="1" stop-color="#3563D2"/></linearGradient>
            <linearGradient id="evxCone{{ $uid }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFF4D6" stop-opacity=".05"/><stop offset="1" stop-color="#FFF4D6" stop-opacity=".30"/></linearGradient>
            <radialGradient id="evxPool{{ $uid }}"><stop offset="0" stop-color="#FFF4D6" stop-opacity=".75"/><stop offset="1" stop-color="#FFF4D6" stop-opacity="0"/></radialGradient>
            <linearGradient id="evxCurtain{{ $uid }}" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#2F5FCF"/><stop offset="1" stop-color="#3B6FE0"/></linearGradient>
        </defs>

        <rect width="360" height="176" fill="url(#evxWall{{ $uid }})"/>

        {{-- Stage floor, boards running toward the back, front lip. --}}
        <polygon points="36,123 324,123 367,158 -7,158" fill="url(#evxFloor{{ $uid }})"/>
        <g stroke="#fff" stroke-opacity=".07" stroke-width="1">
            <line x1="72" y1="123" x2="40" y2="158"/><line x1="108" y1="123" x2="87" y2="158"/><line x1="144" y1="123" x2="133" y2="158"/>
            <line x1="180" y1="123" x2="180" y2="158"/><line x1="216" y1="123" x2="227" y2="158"/><line x1="252" y1="123" x2="273" y2="158"/><line x1="288" y1="123" x2="320" y2="158"/>
        </g>
        <rect y="158" width="360" height="18" fill="#142766"/>
        <line x1="0" y1="158" x2="360" y2="158" stroke="#fff" stroke-opacity=".18"/>

        {{-- The spot: rig, lamp, cone, pool, dust. --}}
        <line x1="72" y1="10.5" x2="288" y2="10.5" stroke="#5B7FD0" stroke-width="2"/>
        <g class="evx__spot">
            <polygon points="173,17.6 187,17.6 241,141 119,141" fill="url(#evxCone{{ $uid }})"/>
            <ellipse cx="180" cy="141" rx="61" ry="13.4" fill="url(#evxPool{{ $uid }})"/>
            <g fill="#FFF4D6" fill-opacity=".55">
                <circle cx="165.6" cy="63.4" r="1.4"/><circle cx="198" cy="82.7" r="1.1"/><circle cx="172.8" cy="102" r="1.6"/><circle cx="205.2" cy="112.6" r="1"/><circle cx="151.2" cy="123.2" r="1.2"/>
            </g>
        </g>
        <rect class="evx__lamp" x="171" y="10.5" width="18" height="8" rx="2.5"/>

        {{-- A lone mic stand in the pool — the act hasn't walked on yet. --}}
        <g class="evx__mic" stroke-width="2" stroke-linecap="round" fill="none">
            <line x1="171" y1="141" x2="189" y2="141"/>
            <line x1="180" y1="141" x2="180" y2="82.7"/>
            <line x1="180" y1="82.7" x2="187" y2="75.7"/>
            <rect class="evx__mic-head" x="184" y="68.2" width="12" height="8" rx="4" transform="rotate(-30 189 72.2)" stroke="none"/>
        </g>

        {{-- Curtains swagged back to the wings; they slide open on entry. --}}
        <g class="evx__curtain evx__curtain--l">
            <path d="M0,0 L61,0 Q54.9,60 25.6,109 Q18.3,141 29.3,158 L0,158 Z" fill="url(#evxCurtain{{ $uid }})"/>
            <g stroke="#fff" stroke-opacity=".10"><line x1="15.3" y1="0" x2="8.4" y2="109"/><line x1="30.5" y1="0" x2="16.8" y2="109"/><line x1="45.8" y1="0" x2="25.2" y2="109"/></g>
            <circle cx="25.6" cy="109" r="2.6" fill="#FFD27A" fill-opacity=".9"/>
        </g>
        <g class="evx__curtain evx__curtain--r">
            <g transform="translate(360 0) scale(-1 1)">
                <path d="M0,0 L61,0 Q54.9,60 25.6,109 Q18.3,141 29.3,158 L0,158 Z" fill="url(#evxCurtain{{ $uid }})"/>
                <g stroke="#fff" stroke-opacity=".10"><line x1="15.3" y1="0" x2="8.4" y2="109"/><line x1="30.5" y1="0" x2="16.8" y2="109"/><line x1="45.8" y1="0" x2="25.2" y2="109"/></g>
                <circle cx="25.6" cy="109" r="2.6" fill="#FFD27A" fill-opacity=".9"/>
            </g>
        </g>

        {{-- Scalloped valance. --}}
        <g fill="#2950C2">
            <rect width="360" height="17.6"/>
            <ellipse cx="20" cy="17.6" rx="20" ry="8.8"/><ellipse cx="60" cy="17.6" rx="20" ry="8.8"/><ellipse cx="100" cy="17.6" rx="20" ry="8.8"/>
            <ellipse cx="140" cy="17.6" rx="20" ry="8.8"/><ellipse cx="180" cy="17.6" rx="20" ry="8.8"/><ellipse cx="220" cy="17.6" rx="20" ry="8.8"/>
            <ellipse cx="260" cy="17.6" rx="20" ry="8.8"/><ellipse cx="300" cy="17.6" rx="20" ry="8.8"/><ellipse cx="340" cy="17.6" rx="20" ry="8.8"/>
        </g>

        {{-- The ticket: real notches (the hall shows through), perforation, the H on its face. --}}
        <g transform="translate(256 57) rotate(-14 32 17)">
            <g class="evx__ticket">
                <path d="M5,0 H40.8 A4,4 0 0 0 48.8,0 H59 A5,5 0 0 1 64,5 V29 A5,5 0 0 1 59,34 H48.8 A4,4 0 0 0 40.8,34 H5 A5,5 0 0 1 0,29 V5 A5,5 0 0 1 5,0 Z" fill="#fff"/>
                <line x1="44.8" y1="6" x2="44.8" y2="28" stroke="#2563EB" stroke-opacity=".45" stroke-dasharray="2 2"/>
                <svg x="14.6" y="8.2" width="15.5" height="17.6" viewBox="35 32 38 44"><path d="M63.77,32.52L63.74,32.68L63.74,33.26L63.75,42.09L63.66,43.53L63.56,44.08L63.34,44.91L63.16,45.42L62.94,45.95L62.73,46.37L62.30,47.05L61.88,47.56L61.47,47.98L61.09,48.30L60.64,48.62L60.30,48.84L59.78,49.12L59.33,49.31L58.80,49.50L58.21,49.67L57.69,49.79L56.99,49.89L56.21,49.95L51.66,49.96L50.46,50.03L49.35,50.14L48.63,50.25L47.55,50.48L46.16,50.88L45.26,51.21L44.01,51.75L42.99,52.28L41.96,52.94L41.20,53.49L40.43,54.13L39.44,55.07L39.03,55.52L38.59,56.04L37.87,57.03L37.33,57.88L37.00,58.46L36.58,59.35L36.26,60.10L35.94,61.01L35.62,62.14L35.58,62.39L35.51,62.64L35.35,63.63L35.29,63.83L35.28,75.38L35.30,75.49L44.07,75.50L44.18,75.48L44.20,74.87L44.18,65.49L44.25,64.45L44.35,63.80L44.54,63.00L44.71,62.45L45.02,61.72L45.35,61.09L45.79,60.45L46.12,60.06L46.49,59.71L47.00,59.29L47.32,59.07L47.69,58.85L48.09,58.64L48.57,58.42L49.56,58.10L50.59,57.87L51.37,57.78L51.77,57.75L56.21,57.74L57.82,57.62L58.69,57.51L59.84,57.29L61.33,56.87L62.53,56.45L63.76,55.91L64.77,55.37L65.30,55.05L65.94,54.62L66.79,53.97L67.66,53.21L68.62,52.24L69.38,51.32L70.13,50.30L70.67,49.42L71.09,48.59L71.42,47.85L71.84,46.77L72.06,46.08L72.39,44.86L72.42,44.60L72.49,44.35L72.59,43.75L72.65,43.24L72.70,43.04L72.72,42.57L72.71,32.52L72.60,32.50L63.91,32.50L63.77,32.52Z M72.68,50.03L72.62,50.09L72.48,50.46L72.37,50.67L71.83,51.63L71.50,52.15L70.76,53.18L69.79,54.30L69.05,55.03L68.22,55.77L67.69,56.20L66.66,56.94L65.43,57.71L64.59,58.13L64.09,58.35L63.89,58.42L63.77,58.49L63.72,58.55L63.71,75.43L63.72,75.47L63.75,75.49L72.63,75.50L72.71,75.48L72.72,50.19L72.71,50.06L72.68,50.03Z M35.29,32.51L35.28,57.09L35.29,57.22L35.31,57.25L35.38,57.19L35.53,56.80L35.96,55.98L36.50,55.10L37.25,54.08L37.67,53.57L38.55,52.66L39.51,51.80L39.93,51.46L40.49,51.04L41.29,50.50L42.03,50.07L42.45,49.84L43.34,49.42L44.11,49.12L44.20,49.03L44.22,48.91L44.21,33.42L44.20,32.65L44.18,32.53L44.12,32.50L35.29,32.51Z" fill="#2563EB"/></svg>
                <g stroke="#2563EB" stroke-opacity=".35" stroke-width="1.6" stroke-linecap="round">
                    <line x1="49.8" y1="10.9" x2="59" y2="10.9"/><line x1="49.8" y1="17" x2="59" y2="17"/><line x1="49.8" y1="23.1" x2="59" y2="23.1"/>
                </g>
            </g>
        </g>
    </svg>

    @if($evxCat)
        <h3 class="evx__title">No {{ \Illuminate\Support\Str::singular(strtolower($evxCat)) }} events right now</h3>
        <p class="evx__body">Nothing on the bill for that yet. Everything else is one tap away.</p>
        <a class="evx__cta" href="{{ url('/events') }}">Show all events</a>
    @else
        <h3 class="evx__title">No events listed right now</h3>
        <p class="evx__body">The stage is set. New shows land here the moment a host publishes one.</p>
        <a class="evx__cta" href="{{ url('/become-a-partner') }}">Host an event on Haraan</a>
    @endif
</div>

@once
<style>
.evx{grid-column:1/-1;margin:4px 16px 24px;padding:6px 6px 16px;background:#fff;border:1.5px solid #2563EB;border-radius:22px;
     box-shadow:0 10px 24px -8px rgba(37,99,235,.28);text-align:center}
.events-grid-list .evx{margin:8px auto 24px;width:100%;max-width:560px}
.evx__scene{display:block;width:100%;height:176px;border-radius:17px}
.evx__title{margin:14px 14px 0;font-size:15.5px;line-height:1.3;font-weight:800;color:#0F172A;letter-spacing:-.01em}
.evx__body{margin:4px 20px 0;font-size:13px;line-height:18px;color:#64748B}
.evx__cta{display:inline-flex;align-items:center;height:40px;margin-top:14px;padding:0 18px;border-radius:999px;background:#2563EB;
          color:#fff;font-size:13.5px;font-weight:800;text-decoration:none}
.evx__cta:hover{background:#1D4ED8;color:#fff}
.evx__cta:focus-visible{outline:2px solid #2563EB;outline-offset:3px}
.evx__lamp{fill:#FFFBEB}
.evx__mic{stroke:#E8EEF9}
.evx__mic-head{fill:#E8EEF9}
.evx__curtain--l{animation:evxL .75s cubic-bezier(.4,0,.2,1) both}
.evx__curtain--r{animation:evxR .75s cubic-bezier(.4,0,.2,1) both}
.evx__spot{animation:evxOn .52s .7s ease-out both}
.evx__lamp{animation:evxLamp .52s .7s ease-out both}
.evx__mic{animation:evxMic .52s .7s ease-out both}
.evx__mic-head{animation:evxMicFill .52s .7s ease-out both}
.evx__ticket{transform-box:fill-box;transform-origin:center;animation:evxDrop .8s .95s cubic-bezier(.34,1.56,.64,1) both}
@keyframes evxL{from{transform:translateX(118px)}to{transform:none}}
@keyframes evxR{from{transform:translateX(-118px)}to{transform:none}}
@keyframes evxOn{from{opacity:0}to{opacity:1}}
@keyframes evxLamp{from{fill:#5B7FD0}to{fill:#FFFBEB}}
@keyframes evxMic{from{stroke:#7D98D8}to{stroke:#E8EEF9}}
@keyframes evxMicFill{from{fill:#7D98D8}to{fill:#E8EEF9}}
@keyframes evxDrop{from{transform:translateY(-53px) rotate(6deg);opacity:0}to{transform:none;opacity:1}}
@media (prefers-reduced-motion:reduce){.evx *{animation:none!important}}
</style>
@endonce
