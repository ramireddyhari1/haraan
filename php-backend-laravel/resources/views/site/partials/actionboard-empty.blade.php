{{--
    ActionBoard empty state (Live / Finished), drawn — mirrors the app's BoardEmpty.
      kind      'live' | 'finished'
      title     the "All" sentence; data-tpl carries the per-sport one (mabFilter swaps it)
      tpl       "No live {sport} matches right now"
      next      optional ['label' => 'Lions vs Tigers', 'when' => 'Sat · 6:30 PM', 'sport' => 'cricket']
      results   true when the Finished tab has something to show
    Every line is a fact or a door: what's next on the calendar, where results are,
    how to start one. Nothing invented to fill the space.
--}}
@php $isLive = ($kind ?? 'live') === 'live'; @endphp
<div class="mab__empty mab__nil" data-all="{{ $title }}" data-tpl="{{ $tpl }}" @if (! empty($hidden)) hidden @endif>
    @if ($isLive)
        {{-- A ground waiting for a match: scoreboard on dashes, floodlights off,
             stumps up, the ball resting by the crease. --}}
        <svg class="mab__nil-art" viewBox="0 0 280 150" role="img" aria-label="An empty ground with the scoreboard waiting">
            <rect x="0" y="0" width="280" height="150" rx="18" fill="#EEF3FA"/>
            {{-- floodlight masts, lamps off --}}
            <g class="mab__nil-mast">
                <path d="M30 104V30M250 104V30" stroke="#C5D0DF" stroke-width="3" stroke-linecap="round"/>
                <rect x="18" y="18" width="24" height="15" rx="3" fill="#DCE4EF" stroke="#C5D0DF" stroke-width="1.5"/>
                <rect x="238" y="18" width="24" height="15" rx="3" fill="#DCE4EF" stroke="#C5D0DF" stroke-width="1.5"/>
                <path d="M24 22.5h12M24 28.5h12M244 22.5h12M244 28.5h12" stroke="#C5D0DF" stroke-width="1.5" stroke-linecap="round"/>
            </g>
            {{-- the outfield --}}
            <ellipse cx="140" cy="118" rx="132" ry="34" fill="#DDEBDF"/>
            <path d="M24 112c40 6 80 9 116 9s76-3 116-9" stroke="#CFE2D3" stroke-width="7" fill="none" stroke-linecap="round"/>
            {{-- scoreboard --}}
            <g transform="translate(92 26)">
                <path d="M18 46v20M78 46v20" stroke="#94A3B8" stroke-width="3" stroke-linecap="round"/>
                <rect x="0" y="0" width="96" height="48" rx="7" fill="#0F172A"/>
                <rect x="6" y="6" width="34" height="9" rx="4.5" fill="#1E293B"/>
                <circle cx="13" cy="10.5" r="2.4" fill="#475569"/>
                <text x="19" y="13.4" font-family="Plus Jakarta Sans, system-ui, sans-serif" font-size="7" font-weight="800" fill="#64748B" letter-spacing=".08em">LIVE</text>
                <g class="mab__nil-dash" font-family="ui-monospace, SFMono-Regular, Menlo, monospace" font-weight="800" fill="#FBBF24">
                    <text x="48" y="36" font-size="18" text-anchor="middle">– / –</text>
                </g>
                <text x="88" y="13.4" font-family="Plus Jakarta Sans, system-ui, sans-serif" font-size="7" font-weight="700" fill="#475569" text-anchor="end">0.0 ov</text>
            </g>
            {{-- pitch, creases, stumps --}}
            <path d="M118 96h44l9 34h-62z" fill="#E9DCC0"/>
            <path d="M115 102h50M108 124h64" stroke="#FFFFFF" stroke-width="1.6" stroke-linecap="round" opacity=".9"/>
            <path d="M136 92v8M140 92v8M144 92v8" stroke="#8B6B3E" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M135 92h10" stroke="#8B6B3E" stroke-width="1.4" stroke-linecap="round"/>
            <path d="M134.5 117v10M140 117v10M145.5 117v10" stroke="#8B6B3E" stroke-width="2.2" stroke-linecap="round"/>
            <path d="M133.5 117h13" stroke="#8B6B3E" stroke-width="1.6" stroke-linecap="round"/>
            {{-- the ball, at rest --}}
            <ellipse cx="165" cy="131.5" rx="6" ry="1.6" fill="#0F172A" opacity=".14"/>
            <g class="mab__nil-ball">
                <circle cx="165" cy="126" r="5.2" fill="#DC2626"/>
                <path d="M161.6 122.6c1.8 1.6 2.6 4 2.4 6.8M164.6 121.1c1.8 1.6 2.6 4 2.4 6.8" stroke="#FCA5A5" stroke-width=".9" fill="none" stroke-linecap="round"/>
            </g>
        </svg>
    @else
        {{-- The scorebook, open on a blank page, pencil down. --}}
        <svg class="mab__nil-art" viewBox="0 0 280 150" role="img" aria-label="An open scorebook with a blank page">
            <rect x="0" y="0" width="280" height="150" rx="18" fill="#EEF3FA"/>
            <ellipse cx="140" cy="132" rx="92" ry="7" fill="#0F172A" opacity=".08"/>
            <path d="M52 34c30-6 60-4 88 8v88c-28-12-58-14-88-8z" fill="#FFFFFF" stroke="#D5DEEC" stroke-width="1.5"/>
            <path d="M228 34c-30-6-60-4-88 8v88c28-12 58-14 88-8z" fill="#FFFFFF" stroke="#D5DEEC" stroke-width="1.5"/>
            <path d="M140 42v88" stroke="#C5D0DF" stroke-width="1.5"/>
            <g stroke="#E2E8F0" stroke-width="1.2" stroke-linecap="round">
                <path d="M62 52c22-3 44-2 66 5M62 66c22-3 44-2 66 5M62 80c22-3 44-2 66 5M62 94c22-3 44-2 66 5M62 108c22-3 44-2 66 5"/>
                <path d="M152 57c22-7 44-8 66-5M152 71c22-7 44-8 66-5M152 85c22-7 44-8 66-5M152 99c22-7 44-8 66-5M152 113c22-7 44-8 66-5"/>
            </g>
            <text x="95" y="49" font-family="Plus Jakarta Sans, system-ui, sans-serif" font-size="8" font-weight="800" fill="#94A3B8" text-anchor="middle" letter-spacing=".12em" transform="rotate(4 95 49)">RESULT</text>
            <g class="mab__nil-dash" font-family="ui-monospace, SFMono-Regular, Menlo, monospace" font-weight="800" fill="#CBD5E1">
                <text x="185" y="80" font-size="16" text-anchor="middle" transform="rotate(-5 185 80)">– / –</text>
            </g>
            <path d="M152 36l7 0 0 22-3.5-3-3.5 3z" fill="#2563EB"/>
            {{-- pencil --}}
            <g class="mab__nil-pencil" transform="rotate(-32 200 112)">
                <rect x="166" y="108" width="54" height="8" rx="1.5" fill="#FBBF24"/>
                <rect x="216" y="108" width="8" height="8" rx="1.5" fill="#F9A8D4"/>
                <rect x="212" y="108" width="4" height="8" fill="#94A3B8"/>
                <path d="M166 108l-9 4 9 4z" fill="#F5D7A1"/>
                <path d="M159.5 110.9l-2.5 1.1 2.5 1.1z" fill="#334155"/>
            </g>
        </svg>
    @endif

    <strong class="mab__nil-title">{{ $title }}</strong>

    @if ($isLive && ! empty($next))
        <button type="button" class="mab__nil-next" data-next-sport="{{ $next['sport'] ?? '' }}" onclick="mabTab(2)">
            <span class="mab__nil-next-k">Next up</span>
            <span class="mab__nil-next-v">{{ $next['label'] }}</span>
            <span class="mab__nil-next-w">{{ $next['when'] }}</span>
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
        </button>
    @else
        <p class="mab__nil-sub">
            {{ $isLive
                ? 'When a match near you starts, its score lands here ball by ball.'
                : 'Scorecards land here the moment a match near you ends.' }}
        </p>
    @endif

    <div class="mab__nil-actions">
        <a class="mab__nil-go" href="{{ route('site.gamehub.actionboard.create') }}">
            <i aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></i>
            Create a match
        </a>
        @if ($isLive && ! empty($results))
            <button type="button" class="mab__nil-alt" onclick="mabTab(1)">Recent results</button>
        @elseif (! $isLive)
            <button type="button" class="mab__nil-alt" onclick="mabTab(2)">Scheduled</button>
        @endif
    </div>
</div>
