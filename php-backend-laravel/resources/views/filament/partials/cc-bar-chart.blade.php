{{-- Takings bar chart. Expects $series from App\Support\TakingsSeries::build(); optional $emptyText. --}}
@php
        // ── Takings chart geometry (viewBox units) ──────────────────────────
        $cw = 720; $ch = 210; $padL = 48; $padR = 8; $padT = 26; $padB = 26;
        $plotW = $cw - $padL - $padR; $plotH = $ch - $padT - $padB;
        $bars = $series['bars'] ?? [];
        $n = max(1, count($bars));
        $slot = $plotW / $n;
        $barW = max(3, min(28, $slot * 0.62));
        $max = (float) ($series['max'] ?? 0);
        // Round the axis top up to a friendly number so gridlines land on clean values.
        $niceMax = 0;
        if ($max > 0) {
            $mag = 10 ** floor(log10($max));
            foreach ([1, 2, 2.5, 5, 10] as $m) { if ($m * $mag >= $max) { $niceMax = $m * $mag; break; } }
        }
        $yOf = fn (float $v) => $padT + $plotH - ($niceMax > 0 ? $v / $niceMax * $plotH : 0);
        $peak = $series['peak'];

@endphp
@if ($series['empty'])
    <div class="cc-empty-chart">
        {{-- An empty till: drawn only when there is genuinely nothing to chart. --}}
        <svg width="120" height="70" viewBox="0 0 120 70" fill="none" aria-hidden="true">
            <path d="M8 62h104" stroke="var(--cc-line)" stroke-width="1.5" stroke-linecap="round"/>
            <rect x="20" y="52" width="10" height="10" rx="2" fill="var(--cc-line-2)"/>
            <rect x="38" y="48" width="10" height="14" rx="2" fill="var(--cc-line-2)"/>
            <rect x="56" y="54" width="10" height="8" rx="2" fill="var(--cc-line-2)"/>
            <rect x="74" y="50" width="10" height="12" rx="2" fill="var(--cc-line-2)"/>
            <rect x="92" y="56" width="10" height="6" rx="2" fill="var(--cc-line-2)"/>
            <path d="M58 10c0-3 2-5 5-5h1c3 0 5 2 5 5v1c0 3-2 4-4 5-1 1-2 2-2 4" stroke="var(--cc-ink-3)" stroke-width="2" stroke-linecap="round"/>
            <circle cx="63" cy="28" r="1.6" fill="var(--cc-ink-3)"/>
        </svg>
        <span>{{ $emptyText ?? 'Nothing to chart yet.' }}</span>
    </div>
@else
    <svg class="cc-chart" viewBox="0 0 {{ $cw }} {{ $ch }}" role="img" aria-label="Paid takings per {{ $series['unit'] }}">
        @foreach ([0, 0.5, 1] as $g)
            @php $gy = $padT + $plotH - $g * $plotH; @endphp
            <line class="{{ $g === 0 ? 'base' : 'grid' }}" x1="{{ $padL }}" x2="{{ $cw - $padR }}" y1="{{ $gy }}" y2="{{ $gy }}" />
            <text x="{{ $padL - 8 }}" y="{{ $gy + 3.5 }}" text-anchor="end">{{ \App\Support\Rupees::short($niceMax * $g) }}</text>
        @endforeach

        @foreach ($bars as $i => $b)
            @php
                $cx = $padL + $slot * $i + $slot / 2;
                $top = $yOf((float) $b['value']);
                $bh = max($b['value'] > 0 ? 2 : 0, $padT + $plotH - $top);
                $tipW = max(96, mb_strlen($b['label']) * 6 + 16);
                $tipX = min(max($cx - $tipW / 2, $padL), $cw - $padR - $tipW);
            @endphp
            <g class="col">
                <rect class="hit" x="{{ $cx - $slot / 2 }}" y="{{ $padT }}" width="{{ $slot }}" height="{{ $plotH }}" />
                @if ($bh > 0)
                    <path class="bar {{ $b['current'] ? 'now' : '' }}"
                          d="M{{ $cx - $barW / 2 }},{{ $padT + $plotH }} V{{ $padT + $plotH - $bh + min(3, $bh) }} q0,-{{ min(3, $bh) }} {{ min(3, $bh) }},-{{ min(3, $bh) }} H{{ $cx + $barW / 2 - min(3, $bh) }} q{{ min(3, $bh) }},0 {{ min(3, $bh) }},{{ min(3, $bh) }} V{{ $padT + $plotH }} Z" />
                @endif
                @if ($b['tick'] !== '')
                    <text x="{{ $cx }}" y="{{ $ch - 6 }}" text-anchor="middle">{{ $b['tick'] }}</text>
                @endif
                <g class="tip">
                    <rect x="{{ $tipX }}" y="0" width="{{ $tipW }}" height="20" rx="6" fill="var(--cc-ink)" />
                    <text x="{{ $tipX + $tipW / 2 }}" y="13.5" text-anchor="middle" style="fill: var(--cc-surface); font-weight: 600">{{ $b['label'] }} · {{ $b['fmt'] }}</text>
                </g>
                <title>{{ $b['label'] }}: {{ $b['fmt'] }} from {{ $b['orders'] }} {{ $b['orders'] === 1 ? 'booking' : 'bookings' }}</title>
            </g>
        @endforeach

        @if (! is_null($peak))
            @php
                $pb = $bars[$peak];
                $px = $padL + $slot * $peak + $slot / 2;
                $py = $yOf((float) $pb['value']);
                $label = 'Best '.$series['unit'].': '.$pb['fmt'];
                $lw = mb_strlen($label) * 6 + 18;
                $lx = min(max($px - $lw / 2, $padL), $cw - $padR - $lw);
            @endphp
            <g class="callout" aria-hidden="true">
                <rect x="{{ $lx }}" y="{{ max(0, $py - 26) }}" width="{{ $lw }}" height="19" rx="6" />
                <text x="{{ $lx + $lw / 2 }}" y="{{ max(0, $py - 26) + 13 }}" text-anchor="middle">{{ $label }}</text>
            </g>
        @endif
    </svg>
@endif

