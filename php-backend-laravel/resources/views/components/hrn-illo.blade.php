{{-- Haraan line-art spot illustrations for /control and /partner: thin ink strokes on a soft
     blue wash, the same hand as the drawn courts (filament/venue/court-pitch). Used for empty
     states and section art, never as decoration on a figure that has data.

     <x-hrn-illo name="court-empty" />            names: see the @switch below
     <x-hrn-illo name="trophy" :size="96" />

     Colours come from --illo-ink / --illo-accent / --illo-soft so a page can tint them; the
     defaults follow light and dark mode. --}}
@props(['name' => 'court-empty', 'size' => 120, 'label' => null])
@php
    $ink = 'var(--illo-ink)';
    $acc = 'var(--illo-accent)';
    $soft = 'var(--illo-soft)';
    $paper = 'var(--illo-paper)';
@endphp
@once
    <style>
        .hrn-illo { --illo-ink: #1f2a44; --illo-accent: #2563eb; --illo-soft: #e8efff; --illo-paper: #ffffff;
            display: block; height: auto; flex: none; overflow: visible; }
        .dark .hrn-illo { --illo-ink: #c7d2e6; --illo-accent: #6f9bff; --illo-soft: #16213a; --illo-paper: #0f172a; }
        .hrn-illo .il-float { animation: hrn-illo-float 4.5s ease-in-out infinite; transform-box: fill-box; transform-origin: center; }
        @keyframes hrn-illo-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-2.5px); } }
        @media (prefers-reduced-motion: reduce) { .hrn-illo .il-float { animation: none; } }
    </style>
@endonce
<svg {{ $attributes->class('hrn-illo') }} width="{{ $size }}" viewBox="0 0 160 110" fill="none"
     stroke-linecap="round" stroke-linejoin="round"
     @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif>
    {{-- Ground shadow every scene stands on. --}}
    <ellipse cx="80" cy="100" rx="58" ry="5" fill="{{ $soft }}"/>

    @switch($name)
        @case('court-empty')
            {{-- A court seen from the baseline: net, service lines, one ball waiting. --}}
            <path d="M28 92 52 30h56l24 62Z" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M80 30v62M40 61h80M45 48h70" stroke="{{ $ink }}" stroke-width="1" opacity=".55"/>
            <path d="M36 64h88" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M36 64v-9h88v9" stroke="{{ $ink }}" stroke-width="1.2"/>
            <path d="M40 55v9M48 55v9M56 55v9M64 55v9M72 55v9M80 55v9M88 55v9M96 55v9M104 55v9M112 55v9M120 55v9" stroke="{{ $ink }}" stroke-width=".6" opacity=".45"/>
            <g class="il-float"><circle cx="104" cy="80" r="5" fill="{{ $acc }}"/><path d="M100.5 77.5c2 1.6 5 1.6 7 0M100.5 82.5c2-1.6 5-1.6 7 0" stroke="{{ $paper }}" stroke-width=".9"/></g>
            @break

        @case('whistle')
            {{-- A coach's whistle on its lanyard, hung up: nobody on shift. --}}
            <path d="M80 8v8" stroke="{{ $ink }}" stroke-width="1.6"/>
            <circle cx="80" cy="18" r="2.5" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="M80 20 58 64M80 20l22 44" stroke="{{ $acc }}" stroke-width="2"/>
            <g class="il-float">
                <path d="M52 66h30a14 14 0 1 1-14 14H52a6 6 0 0 1 0-12Z" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.6"/>
                <circle cx="70" cy="80" r="5" stroke="{{ $ink }}" stroke-width="1.2"/>
                <path d="M86 70h16" stroke="{{ $ink }}" stroke-width="1.6"/>
                <rect x="100" y="66" width="10" height="8" rx="2" fill="{{ $acc }}"/>
            </g>
            <path d="M118 30c4 2 6 6 6 10M124 24c6 3 9 9 9 16" stroke="{{ $ink }}" stroke-width="1.1" opacity=".35"/>
            @break

        @case('trophy')
            <path d="M58 92h44v-8H58Z" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M66 84h28v-8H66Z" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="M76 76v-10h8v10" stroke="{{ $ink }}" stroke-width="1.4"/>
            <g class="il-float">
                <path d="M60 22h40v14a20 20 0 0 1-40 0Z" fill="{{ $acc }}" fill-opacity=".14" stroke="{{ $ink }}" stroke-width="1.6"/>
                <path d="M60 28H50a8 8 0 0 0 10 14M100 28h10a8 8 0 0 1-10 14" stroke="{{ $ink }}" stroke-width="1.4"/>
                <path d="m80 30 2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.6-4.8 2.6.9-5.4-3.9-3.8 5.4-.8Z" fill="{{ $acc }}"/>
            </g>
            <path d="M40 30l-4-4M120 30l4-4M38 48h-6M122 48h6" stroke="{{ $acc }}" stroke-width="1.4" opacity=".6"/>
            @break

        @case('chat')
            {{-- Two speech bubbles, the answer ticked: no one waiting on support. --}}
            <path d="M30 26h62a8 8 0 0 1 8 8v24a8 8 0 0 1-8 8H52l-12 10v-10h-2a8 8 0 0 1-8-8V34a8 8 0 0 1 8-8Z" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M44 40h40M44 50h26" stroke="{{ $ink }}" stroke-width="1.4" opacity=".5"/>
            <g class="il-float">
                <path d="M78 52h46a8 8 0 0 1 8 8v18a8 8 0 0 1-8 8h-4v9l-11-9H78a8 8 0 0 1-8-8V60a8 8 0 0 1 8-8Z" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.6"/>
                <path d="m92 69 6 6 12-12" stroke="{{ $acc }}" stroke-width="2.4"/>
            </g>
            @break

        @case('paper-plane')
            {{-- Messages away: a plane with a dotted trail and a tick. --}}
            <path d="M22 84c18-2 30-12 38-26" stroke="{{ $ink }}" stroke-width="1.2" stroke-dasharray="2 4" opacity=".5"/>
            <g class="il-float">
                <path d="m60 56 74-32-26 58-16-18Z" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.6"/>
                <path d="m92 64 42-40-50 30Z" fill="{{ $acc }}" fill-opacity=".22" stroke="{{ $ink }}" stroke-width="1.2"/>
                <path d="m92 64-2 16 10-9" stroke="{{ $ink }}" stroke-width="1.2"/>
            </g>
            <circle cx="36" cy="34" r="11" fill="{{ $acc }}"/>
            <path d="m31 34 3.5 3.5 6.5-7" stroke="{{ $paper }}" stroke-width="2"/>
            @break

        @case('price-tag')
            {{-- A price tag and a clock: price rules by time. --}}
            <g class="il-float">
                <path d="M46 30h36l30 30-30 30-36-36Z" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.6"/>
                <circle cx="60" cy="44" r="5" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.4"/>
                <path d="M74 60h18M74 68h12" stroke="{{ $ink }}" stroke-width="1.4" opacity=".55"/>
                <path d="M60 39c-6-12-2-22 6-24" stroke="{{ $ink }}" stroke-width="1.2"/>
            </g>
            <circle cx="122" cy="34" r="14" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M122 26v8l6 4" stroke="{{ $acc }}" stroke-width="2"/>
            @break

        @case('calendar')
            <rect x="40" y="24" width="80" height="66" rx="8" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M40 40h80" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="M40 32a8 8 0 0 1 8-8h64a8 8 0 0 1 8 8v8H40Z" fill="{{ $acc }}" fill-opacity=".16"/>
            <path d="M56 18v12M104 18v12" stroke="{{ $ink }}" stroke-width="2"/>
            @foreach ([[54, 52], [70, 52], [86, 52], [102, 52], [54, 66], [70, 66], [86, 66], [54, 80], [70, 80]] as [$x, $y])
                <rect x="{{ $x - 4 }}" y="{{ $y - 4 }}" width="8" height="8" rx="2" fill="{{ $soft }}"/>
            @endforeach
            <g class="il-float"><circle cx="102" cy="73" r="9" fill="{{ $acc }}"/><path d="M96 70c3.5 2.4 8.5 2.4 12 0M96 76c3.5-2.4 8.5-2.4 12 0" stroke="{{ $paper }}" stroke-width="1"/></g>
            @break

        @case('players')
            {{-- Two players with rackets: the people who book. --}}
            <circle cx="58" cy="30" r="9" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M44 92V62a14 14 0 0 1 28 0v30" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M72 58l14-10" stroke="{{ $ink }}" stroke-width="1.6"/>
            <ellipse cx="92" cy="42" rx="7" ry="9" transform="rotate(35 92 42)" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <circle cx="106" cy="38" r="8" fill="{{ $acc }}" fill-opacity=".18" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M94 92V66a12 12 0 0 1 24 0v26" fill="{{ $acc }}" fill-opacity=".14" stroke="{{ $ink }}" stroke-width="1.6"/>
            <g class="il-float"><circle cx="124" cy="22" r="4" fill="{{ $acc }}"/></g>
            @break

        @case('plug')
            {{-- Two halves of a plug meeting: integrations. --}}
            <path d="M14 62h30" stroke="{{ $ink }}" stroke-width="2"/>
            <rect x="44" y="48" width="26" height="28" rx="6" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M70 56h10M70 68h10" stroke="{{ $ink }}" stroke-width="2.2"/>
            <g class="il-float">
                <rect x="88" y="48" width="26" height="28" rx="6" fill="{{ $acc }}" fill-opacity=".16" stroke="{{ $ink }}" stroke-width="1.6"/>
                <path d="M114 62h32" stroke="{{ $ink }}" stroke-width="2"/>
            </g>
            <path d="M80 34l2 6M80 90l2-6M68 36l4 5M94 36l-4 5" stroke="{{ $acc }}" stroke-width="1.6"/>
            @break

        @case('photo')
            {{-- An empty frame with a plus: add a photo. --}}
            <rect x="34" y="22" width="92" height="66" rx="8" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.6" stroke-dasharray="4 4"/>
            <path d="m42 80 22-24 14 14 12-10 28 20Z" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <circle cx="104" cy="40" r="6" fill="{{ $acc }}" fill-opacity=".22" stroke="{{ $ink }}" stroke-width="1.2"/>
            <g class="il-float"><circle cx="124" cy="24" r="11" fill="{{ $acc }}"/><path d="M124 19v10M119 24h10" stroke="{{ $paper }}" stroke-width="2"/></g>
            @break

        @case('receipt')
            <path d="M48 16h64v76l-8-5-8 5-8-5-8 5-8-5-8 5-8-5-8 5Z" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M58 32h44M58 44h30M58 56h36" stroke="{{ $ink }}" stroke-width="1.4" opacity=".5"/>
            <path d="M58 70h44" stroke="{{ $ink }}" stroke-width="1.6"/>
            <g class="il-float"><circle cx="112" cy="70" r="12" fill="{{ $acc }}"/><path d="M107 64h10M107 68h10M110 64c5 0 5 8 0 8l7 6" stroke="{{ $paper }}" stroke-width="1.6"/></g>
            @break

        @case('stadium-lights')
            {{-- A floodlight over a pitch: the venue itself. --}}
            <path d="M24 92 40 66h80l16 26Z" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M80 66v26M52 79h56" stroke="{{ $ink }}" stroke-width="1" opacity=".5"/>
            <path d="M46 66V22M114 66V22" stroke="{{ $ink }}" stroke-width="1.8"/>
            <rect x="36" y="12" width="20" height="12" rx="2" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <rect x="104" y="12" width="20" height="12" rx="2" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="M40 18h12M108 18h12" stroke="{{ $acc }}" stroke-width="2"/>
            <path d="M46 24 62 62M114 24 98 62" stroke="{{ $acc }}" stroke-width="1" opacity=".35" stroke-dasharray="2 3"/>
            @break

        @case('map')
            {{-- A folded city map with a pin: where things sold. --}}
            <path d="M28 30 58 20l44 12 30-10v62l-30 10-44-12-30 10Z" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M58 20v62M102 32v62" stroke="{{ $ink }}" stroke-width="1.2" opacity=".5"/>
            <path d="M36 60c10-8 18 4 28-2s16-14 26-8 18 6 30-4" stroke="{{ $acc }}" stroke-width="1.4" stroke-dasharray="3 3" opacity=".7"/>
            <path d="M28 30 58 20v62l-30 10Z" fill="{{ $soft }}"/>
            <g class="il-float">
                <path d="M88 16c-9 0-15 6.5-15 14.5C73 42 88 56 88 56s15-14 15-25.5C103 22.5 97 16 88 16Z" fill="{{ $acc }}"/>
                <circle cx="88" cy="30" r="5" fill="{{ $paper }}"/>
            </g>
            @break

        @case('clipboard')
            {{-- A clipboard with a pen: the audit log, nothing written yet. --}}
            <rect x="46" y="18" width="62" height="76" rx="8" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <rect x="64" y="12" width="26" height="12" rx="4" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="M58 40h38M58 52h30M58 64h34M58 76h22" stroke="{{ $ink }}" stroke-width="1.4" opacity=".35"/>
            <g class="il-float">
                <path d="m112 34 10 10-30 30-13 3 3-13Z" fill="{{ $acc }}" fill-opacity=".18" stroke="{{ $ink }}" stroke-width="1.4"/>
                <path d="m106 40 10 10" stroke="{{ $ink }}" stroke-width="1.2"/>
                <path d="m82 64 10 10" stroke="{{ $acc }}" stroke-width="2"/>
            </g>
            @break

        @case('ticket')
            {{-- A ticket with a torn stub: orders and sales. --}}
            <path d="M30 34a6 6 0 0 0 6-6h70a6 6 0 0 0 6 6v8a8 8 0 0 0 0 16v8a6 6 0 0 0-6 6H36a6 6 0 0 0-6-6v-8a8 8 0 0 0 0-16Z" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M86 32v44" stroke="{{ $ink }}" stroke-width="1.2" stroke-dasharray="2 4" opacity=".6"/>
            <path d="M44 46h30M44 56h20M44 64h24" stroke="{{ $ink }}" stroke-width="1.4" opacity=".4"/>
            <g class="il-float">
                <path d="M104 22h22a4 4 0 0 1 4 4v8a6 6 0 0 0 0 12v8a4 4 0 0 1-4 4h-22Z" fill="{{ $acc }}" fill-opacity=".18" stroke="{{ $ink }}" stroke-width="1.4" transform="rotate(12 117 40)"/>
                <path d="m112 40 4 4 7-8" stroke="{{ $acc }}" stroke-width="2" transform="rotate(12 117 40)"/>
            </g>
            @break

        @case('stage')
            {{-- A stage under two spotlights, curtains open: what's on. --}}
            <path d="M22 82h116v10H22Z" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M30 18h100" stroke="{{ $ink }}" stroke-width="2"/>
            <path d="M32 18c0 24 4 48 14 64H32ZM128 18c0 24-4 48-14 64h14Z" fill="{{ $acc }}" fill-opacity=".16" stroke="{{ $ink }}" stroke-width="1.4"/>
            <path d="m56 22 14 60M104 22 90 82" stroke="{{ $acc }}" stroke-width="1" opacity=".35" stroke-dasharray="2 3"/>
            <path d="M52 18l4 6h-8ZM108 18l4 6h-8Z" fill="{{ $ink }}"/>
            <g class="il-float">
                <path d="M74 74V58a6 6 0 0 1 12 0v16" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.4"/>
                <circle cx="80" cy="48" r="5" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.4"/>
                <path d="M86 60l8-6" stroke="{{ $ink }}" stroke-width="1.4"/>
                <circle cx="95" cy="53" r="2" fill="{{ $acc }}"/>
            </g>
            @break

        @case('switchboard')
            {{-- A breaker panel: the platform's emergency switches. Tint --illo-accent red when any are on. --}}
            <rect x="34" y="16" width="92" height="78" rx="8" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="1.6"/>
            <path d="M34 30h92" stroke="{{ $ink }}" stroke-width="1.2" opacity=".5"/>
            <circle cx="42" cy="23" r="2" fill="{{ $ink }}" opacity=".5"/><circle cx="118" cy="23" r="2" fill="{{ $ink }}" opacity=".5"/>
            @foreach ([48, 70, 92] as $i => $x)
                <rect x="{{ $x }}" y="40" width="16" height="42" rx="4" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.3"/>
                <rect x="{{ $x + 3 }}" y="{{ $i === 1 ? 62 : 44 }}" width="10" height="14" rx="2" fill="{{ $i === 1 ? $acc : $paper }}" stroke="{{ $ink }}" stroke-width="1.1"/>
            @endforeach
            <g class="il-float"><circle cx="128" cy="14" r="8" fill="{{ $acc }}"/><path d="M128 9v5" stroke="{{ $paper }}" stroke-width="2"/><path d="M124.5 11.5a5 5 0 1 0 7 0" stroke="{{ $paper }}" stroke-width="1.6"/></g>
            @break

        @default
            {{-- Unknown name: a quiet dot rather than a broken image. --}}
            <circle cx="80" cy="56" r="20" fill="{{ $soft }}" stroke="{{ $ink }}" stroke-width="1.6"/>
    @endswitch
</svg>
