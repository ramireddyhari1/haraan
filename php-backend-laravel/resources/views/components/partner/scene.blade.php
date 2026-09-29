{{--
    The partner home's drawn scene — the partner's own kind of place, lit by the
    actual time of day. A turf's floodlights are on after dark; a café's pendant
    lamps glow in the evening; a stage is always an indoor night.

    Props:
      kind   turf | stage | cafe
      phase  morning | day | evening | night   (the hero computes it from the clock)

    Pure inline SVG, no raster, no external request. Every id is suffixed with a
    per-render uid so two scenes on one page can't cross-reference gradients.
    Motion is a single entrance (ball rolls in, beams settle, steam rises a few
    times) and then the picture holds still — nothing loops forever.
--}}
@props(['kind' => 'turf', 'phase' => 'day'])

@php
    $u = 's' . substr(md5(uniqid('', true)), 0, 6);
    $lit = in_array($phase, ['evening', 'night'], true);

    $sky = [
        'morning' => ['#D9E8FF', '#FFF0DA'],
        'day' => ['#CFE2FF', '#F1F7FF'],
        'evening' => ['#2D3F8E', '#F2A25E'],
        'night' => ['#0D1A44', '#23387C'],
    ][$phase] ?? ['#CFE2FF', '#F1F7FF'];

    $hill = ['morning' => '#C3D8F4', 'day' => '#B7D0F2', 'evening' => '#3A4A86', 'night' => '#18285C'][$phase] ?? '#B7D0F2';
    $hill2 = ['morning' => '#AFC9EE', 'day' => '#A3C2EC', 'evening' => '#2C3B73', 'night' => '#13224F'][$phase] ?? '#A3C2EC';
    $pole = $lit ? '#8E9BBF' : '#5B6B8C';
@endphp

<svg {{ $attributes->merge(['class' => 'hrn-scene hrn-scene-' . $kind . ' is-' . $phase]) }}
     viewBox="0 0 360 200" preserveAspectRatio="xMidYMid slice" role="img"
     aria-label="{{ match ($kind) { 'stage' => 'A stage under lights', 'cafe' => 'A café counter', default => 'A floodlit turf' } }}">
    <defs>
        <linearGradient id="{{ $u }}-sky" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="{{ $sky[0] }}"/>
            <stop offset="1" stop-color="{{ $sky[1] }}"/>
        </linearGradient>
        <linearGradient id="{{ $u }}-cone" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#FFF4C2" stop-opacity=".75"/>
            <stop offset="1" stop-color="#FFF4C2" stop-opacity="0"/>
        </linearGradient>
        <radialGradient id="{{ $u }}-glow">
            <stop offset="0" stop-color="#FFF6CF" stop-opacity=".95"/>
            <stop offset="1" stop-color="#FFF6CF" stop-opacity="0"/>
        </radialGradient>
    </defs>

    @if ($kind === 'stage')
        {{-- ── An indoor night: truss, three lamps, a stage, the first rows. ── --}}
        <rect width="360" height="200" fill="#101938"/>
        <rect width="360" height="200" fill="url(#{{ $u }}-glow)" opacity=".08"/>

        {{-- back wall panels --}}
        <g stroke="#1C2A5A" stroke-width="1">
            @foreach (range(0, 8) as $i)
                <line x1="{{ 20 + $i * 40 }}" y1="30" x2="{{ 20 + $i * 40 }}" y2="126"/>
            @endforeach
        </g>

        {{-- beams (settle from a sweep on entrance) --}}
        <g class="hrn-scene-beams" style="mix-blend-mode:screen">
            <polygon class="hrn-beam hrn-beam-l" points="104,32 116,32 170,132 58,132" fill="#8DB2FF" opacity=".22"/>
            <polygon class="hrn-beam hrn-beam-c" points="174,32 186,32 226,132 134,132" fill="#FFD68C" opacity=".26"/>
            <polygon class="hrn-beam hrn-beam-r" points="244,32 256,32 302,132 190,132" fill="#8DB2FF" opacity=".22"/>
        </g>

        {{-- truss --}}
        <g stroke="#6B79A6" stroke-width="1.4" fill="none">
            <line x1="0" y1="18" x2="360" y2="18"/>
            <line x1="0" y1="26" x2="360" y2="26"/>
            <path d="{{ collect(range(0, 17))->map(fn ($i) => 'M' . ($i * 20) . ' 18L' . ($i * 20 + 10) . ' 26L' . ($i * 20 + 20) . ' 18')->implode(' ') }}"/>
        </g>
        @foreach ([110, 180, 250] as $x)
            <g>
                <line x1="{{ $x }}" y1="26" x2="{{ $x }}" y2="30" stroke="#6B79A6" stroke-width="1.4"/>
                <rect x="{{ $x - 9 }}" y="29" width="18" height="8" rx="2" fill="#3A4775" stroke="#6B79A6" stroke-width="1"/>
                <ellipse cx="{{ $x }}" cy="37" rx="6" ry="1.8" fill="#FFF3C4"/>
            </g>
        @endforeach

        {{-- stage --}}
        <rect x="24" y="126" width="312" height="14" rx="2" fill="#2C3C78"/>
        <rect x="24" y="126" width="312" height="2" fill="#4A5EA6"/>
        <rect x="30" y="140" width="300" height="12" fill="#18234F"/>

        {{-- speakers --}}
        <g fill="#1A2450" stroke="#56659A" stroke-width="1">
            <rect x="48" y="100" width="22" height="26" rx="2"/>
            <rect x="290" y="100" width="22" height="26" rx="2"/>
        </g>
        <g fill="none" stroke="#56659A" stroke-width="1">
            <circle cx="59" cy="108" r="3"/><circle cx="59" cy="118" r="5"/>
            <circle cx="301" cy="108" r="3"/><circle cx="301" cy="118" r="5"/>
        </g>

        {{-- mic stand --}}
        <g stroke="#C9D3EE" stroke-width="1.6" stroke-linecap="round" fill="none">
            <line x1="180" y1="126" x2="180" y2="98"/>
            <path d="M172 126l8-5 8 5"/>
        </g>
        <rect x="177" y="90" width="6" height="10" rx="3" fill="#E6ECFA"/>

        {{-- first rows of the crowd --}}
        <g fill="#070D26">
            @foreach ([[18, 176], [52, 170], [86, 178], [118, 168], [152, 176], [188, 170], [222, 177], [256, 168], [290, 175], [326, 171]] as [$x, $y])
                <circle cx="{{ $x }}" cy="{{ $y }}" r="9"/>
                <path d="M{{ $x - 18 }} 200c0-14 8-{{ 200 - $y - 10 }} 18-{{ 200 - $y - 10 }}s18 {{ 200 - $y - 24 }} 18 {{ 200 - $y - 10 }}z"/>
            @endforeach
            <path d="M113 170l-6-24" stroke="#070D26" stroke-width="5" stroke-linecap="round"/>
            <path d="M262 168l7-22" stroke="#070D26" stroke-width="5" stroke-linecap="round"/>
        </g>

    @elseif ($kind === 'cafe')
        {{-- ── A café counter: window to the real sky, pendants, a cup. ── --}}
        <rect width="360" height="200" fill="{{ $lit ? '#3B2C25' : '#FBEFE1' }}"/>

        {{-- window --}}
        <rect x="26" y="24" width="112" height="84" rx="4" fill="url(#{{ $u }}-sky)"/>
        @if ($phase === 'night')
            <circle cx="112" cy="46" r="9" fill="#F4EFDD"/><circle cx="116" cy="43" r="8" fill="{{ $sky[0] }}"/>
        @elseif ($phase !== 'evening')
            <circle cx="110" cy="48" r="9" fill="#FFD166"/>
        @else
            <circle cx="104" cy="96" r="12" fill="#FF9A55"/>
        @endif
        <path d="M26 92c20-10 36-8 56-2s38 4 56-4v22H26z" fill="{{ $hill }}"/>
        <rect x="26" y="24" width="112" height="84" rx="4" fill="none" stroke="{{ $lit ? '#7A5B48' : '#C9A688' }}" stroke-width="4"/>
        <line x1="82" y1="24" x2="82" y2="108" stroke="{{ $lit ? '#7A5B48' : '#C9A688' }}" stroke-width="3"/>

        {{-- pendants --}}
        @foreach ([196, 276] as $x)
            <line x1="{{ $x }}" y1="0" x2="{{ $x }}" y2="30" stroke="{{ $lit ? '#A88A74' : '#8C6F5B' }}" stroke-width="1.2"/>
            @if ($lit)
                <circle cx="{{ $x }}" cy="44" r="34" fill="url(#{{ $u }}-glow)" opacity=".55"/>
            @endif
            <path d="M{{ $x - 15 }} 42a15 13 0 0 1 30 0z" fill="{{ $lit ? '#E9B64C' : '#2F4858' }}"/>
            <ellipse cx="{{ $x }}" cy="42" rx="6" ry="2" fill="{{ $lit ? '#FFF1BF' : '#F6E6CF' }}"/>
        @endforeach

        {{-- espresso machine --}}
        <g>
            <rect x="222" y="84" width="74" height="48" rx="6" fill="#DCE3EC" stroke="#5B6B8C" stroke-width="1.4"/>
            <rect x="222" y="84" width="74" height="10" rx="5" fill="#C3CDDA" stroke="#5B6B8C" stroke-width="1.4"/>
            <circle cx="244" cy="108" r="6" fill="#fff" stroke="#5B6B8C" stroke-width="1.2"/>
            <line x1="244" y1="108" x2="247" y2="105" stroke="#D0445C" stroke-width="1.2" stroke-linecap="round"/>
            <rect x="262" y="114" width="20" height="6" rx="2" fill="#5B6B8C"/>
            <rect x="266" y="122" width="12" height="10" rx="2" fill="#fff" stroke="#5B6B8C" stroke-width="1"/>
        </g>

        {{-- counter --}}
        <rect x="0" y="132" width="360" height="68" fill="{{ $lit ? '#8A5A38' : '#C98B5A' }}"/>
        <rect x="0" y="132" width="360" height="6" fill="{{ $lit ? '#A8714A' : '#E3AA7C' }}"/>
        <g stroke="{{ $lit ? '#76492C' : '#B57A4C' }}" stroke-width="1">
            <line x1="60" y1="144" x2="60" y2="200"/><line x1="150" y1="144" x2="150" y2="200"/>
            <line x1="240" y1="144" x2="240" y2="200"/><line x1="330" y1="144" x2="330" y2="200"/>
        </g>

        {{-- cup + steam --}}
        <ellipse cx="120" cy="132" rx="24" ry="4" fill="#fff" stroke="#5B6B8C" stroke-width="1.2"/>
        <path d="M104 110h32v10a16 12 0 0 1-32 0z" fill="#fff" stroke="#5B6B8C" stroke-width="1.4"/>
        <path d="M136 113c8 0 8 10 0 10" fill="none" stroke="#5B6B8C" stroke-width="1.4"/>
        <ellipse cx="120" cy="110" rx="16" ry="3" fill="#8B5A3C"/>
        <g class="hrn-steam" fill="none" stroke="{{ $lit ? '#E8D6C7' : '#B9A08D' }}" stroke-width="1.6" stroke-linecap="round">
            <path d="M113 102c-4-6 4-9 0-16"/>
            <path d="M121 100c-4-6 4-9 0-16"/>
            <path d="M129 102c-4-6 4-9 0-16"/>
        </g>

    @else
        {{-- ── A caged five-a-side turf under the real sky. ── --}}
        <rect width="360" height="200" fill="url(#{{ $u }}-sky)"/>

        @if ($phase === 'night')
            <g fill="#DDE6FF">
                <circle cx="66" cy="22" r="1"/><circle cx="118" cy="40" r=".8"/><circle cx="168" cy="16" r="1.1"/>
                <circle cx="214" cy="34" r=".8"/><circle cx="252" cy="14" r="1"/><circle cx="148" cy="58" r=".7"/>
            </g>
            <circle cx="290" cy="46" r="14" fill="#F4EFDD"/>
            <circle cx="296" cy="41" r="12.5" fill="{{ $sky[0] }}"/>
        @elseif ($phase === 'evening')
            <circle cx="286" cy="104" r="22" fill="#FF9152" opacity=".9"/>
        @else
            <circle cx="{{ $phase === 'morning' ? 280 : 292 }}" cy="{{ $phase === 'morning' ? 60 : 42 }}" r="16" fill="#FFD166"/>
            <circle cx="{{ $phase === 'morning' ? 280 : 292 }}" cy="{{ $phase === 'morning' ? 60 : 42 }}" r="26" fill="#FFD166" opacity=".18"/>
        @endif

        {{-- skyline --}}
        <path d="M0 108c30-18 58-22 92-12s60 8 92-6 70-14 104 0 50 12 72 8v22H0z" fill="{{ $hill }}"/>
        <g fill="{{ $hill2 }}">
            <rect x="196" y="78" width="14" height="42"/><rect x="212" y="88" width="10" height="32"/>
            <rect x="226" y="70" width="16" height="50"/><rect x="100" y="86" width="12" height="34"/>
        </g>
        @if ($lit)
            <g fill="#FFE8A3" opacity=".8">
                <rect x="229" y="76" width="3" height="3"/><rect x="236" y="84" width="3" height="3"/>
                <rect x="199" y="86" width="3" height="3"/><rect x="103" y="94" width="3" height="3"/>
            </g>
        @endif

        {{-- ground + pitch --}}
        <rect x="0" y="118" width="360" height="82" fill="{{ $lit ? '#1D5E40' : '#2E8F5B' }}"/>
        <clipPath id="{{ $u }}-pitch"><polygon points="70,118 290,118 340,200 20,200"/></clipPath>
        <g clip-path="url(#{{ $u }}-pitch)">
            <rect x="0" y="118" width="360" height="82" fill="{{ $lit ? '#27784F' : '#37A767' }}"/>
            @foreach ([[118, 12], [142, 16], [174, 26]] as [$y, $h])
                <rect x="0" y="{{ $y }}" width="360" height="{{ $h }}" fill="{{ $lit ? '#2D8458' : '#3FB371' }}"/>
            @endforeach
        </g>

        {{-- light pools on the grass --}}
        @if ($lit)
            <ellipse cx="96" cy="176" rx="90" ry="22" fill="#FFF4C2" opacity=".14"/>
            <ellipse cx="264" cy="176" rx="90" ry="22" fill="#FFF4C2" opacity=".14"/>
        @endif

        {{-- markings --}}
        <g fill="none" stroke="#fff" stroke-opacity=".85" stroke-width="1.5" stroke-linejoin="round">
            <polygon points="78,122 282,122 328,194 32,194"/>
            <line x1="180" y1="122" x2="180" y2="194"/>
            <ellipse cx="180" cy="156" rx="26" ry="10"/>
            <path d="M67.5 138H100L82.2 176H42.7"/>
            <path d="M292.5 138H260l17.8 38h39.5"/>
        </g>
        <circle cx="180" cy="156" r="1.6" fill="#fff"/>

        {{-- the cage --}}
        <g stroke="{{ $lit ? '#A9B8DA' : '#3C5A4A' }}" stroke-opacity="{{ $lit ? '.16' : '.22' }}" stroke-width=".8">
            @foreach (range(0, 36) as $i)
                <line x1="{{ $i * 10 }}" y1="70" x2="{{ $i * 10 }}" y2="118"/>
            @endforeach
        </g>
        <line x1="0" y1="70" x2="360" y2="70" stroke="{{ $pole }}" stroke-width="1.4"/>

        {{-- floodlights --}}
        {{-- Kept inside the middle 260px: the panel crops the sides on narrow cards. --}}
        @foreach ([[76, 1], [284, -1]] as [$x, $dir])
            @if ($lit)
                <polygon class="hrn-flood" points="{{ $x - 8 }},36 {{ $x + 8 }},36 {{ 180 + $dir * 10 }},200 {{ $x - $dir * 30 }},200"
                         fill="url(#{{ $u }}-cone)" opacity=".5"/>
                <circle cx="{{ $x }}" cy="32" r="22" fill="url(#{{ $u }}-glow)"/>
            @endif
            <line x1="{{ $x }}" y1="36" x2="{{ $x }}" y2="122" stroke="{{ $pole }}" stroke-width="2.4"/>
            <rect x="{{ $x - 12 }}" y="24" width="24" height="12" rx="2" fill="{{ $lit ? '#3A4775' : '#E6EBF4' }}" stroke="{{ $pole }}" stroke-width="1.2"/>
            @foreach ([-7, 0, 7] as $dx)
                <circle cx="{{ $x + $dx }}" cy="30" r="2.6" fill="{{ $lit ? '#FFF6C8' : '#C9D2E3' }}"/>
            @endforeach
        @endforeach

        {{-- the ball, rolls in and stops --}}
        <g class="hrn-ball">
            <ellipse cx="214" cy="178" rx="7" ry="1.8" fill="#0B2A1A" opacity=".28"/>
            <g class="hrn-ball-spin" style="transform-origin:214px 171px">
                <circle cx="214" cy="171" r="6.5" fill="#fff" stroke="#1F2937" stroke-width="1.1"/>
                <path d="M214 167.6l3 2.2-1.1 3.5h-3.8l-1.1-3.5z" fill="#1F2937"/>
            </g>
        </g>
    @endif
</svg>
