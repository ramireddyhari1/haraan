{{-- Drawn area glyphs for the /control sidebar: the hrn-illo hand at 24px — ink outline, one
     blue-washed shape, one solid accent dot. Each names its area by an object from it, not a
     generic UI icon, so the sidebar reads like Haraan rather than a template.

     <x-hrn-glyph name="events" />   names: command, events, gamehub, finance, marketing,
     rewards, content, support, people, workforce, platform, system --}}
@props(['name' => 'events'])
<svg {{ $attributes->class('hrn-glyph') }} viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('command')
            {{-- A scoreboard: the whole business at a glance. --}}
            <rect x="3" y="4.5" width="18" height="12" rx="2" class="g-wash"/>
            <path d="M12 4.5v12M8 20h8M12 16.5V20"/>
            <path d="M6 9.5h3M6 12h2M15 9.5h3"/>
            <circle cx="16.5" cy="12.4" r="1.3" class="g-dot"/>
            @break

        @case('events')
            {{-- A ticket, tilted, notched on both sides, a star on its stub. --}}
            <g transform="rotate(-12 12 12)">
                <path d="M3 8h18v2.5a1.5 1.5 0 0 0 0 3V16H3v-2.5a1.5 1.5 0 0 0 0-3Z" class="g-wash"/>
                <path d="M15.5 8.5v7" stroke-dasharray="1 1.8"/>
                <path d="M6.5 11h5M6.5 13.2h3"/>
            </g>
            <path d="m18.1 9.9.6 1.3 1.4.2-1 1 .25 1.4-1.25-.66-1.25.66.25-1.4-1-1 1.4-.2Z" class="g-dot"/>
            @break

        @case('gamehub')
            {{-- A court from above, centre line and a ball in play. --}}
            <rect x="3.5" y="5" width="17" height="14" rx="1.5" class="g-wash"/>
            <path d="M12 5v14M3.5 9h3v6h-3M20.5 9h-3v6h3"/>
            <circle cx="12" cy="12" r="2"/>
            <circle cx="16" cy="7.6" r="1.2" class="g-dot"/>
            @break

        @case('finance')
            {{-- A stack of coins beside a rupee note. --}}
            <rect x="2.5" y="6" width="12" height="9" rx="1.5" class="g-wash"/>
            <path d="M6 9h3.5M6 9c2.4 0 2.4 3 0 3l3 2.5M6 10.5h3.5"/>
            <ellipse cx="17.5" cy="11" rx="4" ry="1.6"/>
            <path d="M13.5 11v3.2c0 .9 1.8 1.6 4 1.6s4-.7 4-1.6V11M13.5 14.2v3.2c0 .9 1.8 1.6 4 1.6s4-.7 4-1.6v-3.2"/>
            <circle cx="17.5" cy="11" r=".9" class="g-dot"/>
            @break

        @case('marketing')
            {{-- A megaphone, two sound arcs. --}}
            <path d="M4 10v4h3l7 4V6L7 10Z" class="g-wash"/>
            <path d="M7 14l1.2 4.5h2.3L9.5 14.6"/>
            <path d="M17 9.5a3.5 3.5 0 0 1 0 5M19.2 7.5a6.5 6.5 0 0 1 0 9"/>
            <circle cx="4" cy="12" r="1" class="g-dot"/>
            @break

        @case('rewards')
            {{-- A medal on a ribbon. --}}
            <path d="M8 3h3l1.6 5M16 3h-3l-1.6 5"/>
            <circle cx="12" cy="14.5" r="5.5" class="g-wash"/>
            <path d="m12 11.8.9 1.8 2 .3-1.45 1.4.35 2-1.8-.95-1.8.95.35-2-1.45-1.4 2-.3Z" class="g-dot"/>
            @break

        @case('content')
            {{-- A phone with the home feed's stacked cards. --}}
            <rect x="6.5" y="2.5" width="11" height="19" rx="2.2"/>
            <rect x="8.5" y="5.5" width="7" height="5" rx="1" class="g-wash"/>
            <path d="M8.5 13h7M8.5 15.5h4.5"/>
            <circle cx="12" cy="19" r=".8" class="g-dot"/>
            @break

        @case('support')
            {{-- Two speech bubbles, the reply ticked. --}}
            <path d="M3.5 5h10a1.5 1.5 0 0 1 1.5 1.5v5a1.5 1.5 0 0 1-1.5 1.5H8l-3 2.5V13H3.5A1.5 1.5 0 0 1 2 11.5v-5A1.5 1.5 0 0 1 3.5 5Z"/>
            <path d="M11 10.5h9a1.5 1.5 0 0 1 1.5 1.5v4.5A1.5 1.5 0 0 1 20 18h-.5v2.5L16.5 18H11a1.5 1.5 0 0 1-1.5-1.5V12a1.5 1.5 0 0 1 1.5-1.5Z" class="g-wash"/>
            <path d="m13.2 14.3 1.6 1.6 3-3" class="g-ink-accent"/>
            @break

        @case('people')
            {{-- Two players, one with the ball. --}}
            <circle cx="8.5" cy="7.5" r="2.8"/>
            <path d="M3.5 20v-2.5a5 5 0 0 1 10 0V20"/>
            <circle cx="16.5" cy="9" r="2.4" class="g-wash"/>
            <path d="M13.8 20v-2a4.2 4.2 0 0 1 7.2-2.9" class="g-wash-stroke"/>
            <circle cx="20" cy="19" r="1.4" class="g-dot"/>
            @break

        @case('workforce')
            {{-- A staff ID on its lanyard. --}}
            <path d="M8 2.5 12 7l4-4.5"/>
            <rect x="5.5" y="7" width="13" height="14.5" rx="2" class="g-wash"/>
            <circle cx="12" cy="12.2" r="2"/>
            <path d="M9 17.5h6M10 19.5h4"/>
            <circle cx="12" cy="7" r="1" class="g-dot"/>
            @break

        @case('platform')
            {{-- Three faders on a mixing desk: the switches and rules. --}}
            <rect x="3" y="3.5" width="18" height="17" rx="2" class="g-wash"/>
            <path d="M8 7v10M12 7v10M16 7v10"/>
            <rect x="6.6" y="12" width="2.8" height="2.4" rx=".6" class="g-paper"/>
            <rect x="10.6" y="8" width="2.8" height="2.4" rx=".6" class="g-dot"/>
            <rect x="14.6" y="13.5" width="2.8" height="2.4" rx=".6" class="g-paper"/>
            @break

        @case('system')
            {{-- A shield with a keyhole: access, audit, security. --}}
            <path d="M12 2.8 19.5 5.5V11c0 5-3.2 8.6-7.5 10.2C7.7 19.6 4.5 16 4.5 11V5.5Z" class="g-wash"/>
            <circle cx="12" cy="10.5" r="1.8" class="g-dot"/>
            <path d="M12 12.3v3.4"/>
            @break

        @default
            <circle cx="12" cy="12" r="7" class="g-wash"/>
            <circle cx="12" cy="12" r="1.4" class="g-dot"/>
    @endswitch
</svg>
