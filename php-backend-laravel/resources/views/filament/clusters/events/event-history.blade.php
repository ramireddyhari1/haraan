<x-filament-panels::page>
    @php
        $sum = $this->getHistorySummary();
        $quarters = $this->getQuarterlyTakings();
        $cities = $this->getCityHistory();
        $qW = 64; $qGap = 18; $qH = 120;
    @endphp

    @include('filament.partials.cc-styles')

    <div class="cc">
        <div class="cc-grid">
            <section class="cc-card cc-s8" aria-label="Past events">
                <div class="cc-money-top">
                    <div>
                        <div class="cc-eyebrow">Takings from events already held</div>
                        <div class="cc-gmv cc-num">{{ $sum['revenue'] }}</div>
                    </div>
                    <div class="cc-facts">
                        <div class="cc-fact"><span>Events held</span><b class="cc-num">{{ number_format($sum['events']) }}</b></div>
                        <div class="cc-fact"><span>Tickets</span><b class="cc-num">{{ number_format($sum['tickets']) }}</b></div>
                        <div class="cc-fact" title="{{ $sum['fillNote'] }}"><span>Seats filled</span><b class="cc-num">{{ $sum['fill'] }}</b></div>
                        <div class="cc-fact" title="{{ $sum['showUpNote'] }}"><span>Showed up</span><b class="cc-num">{{ $sum['showUp'] }}</b></div>
                    </div>
                </div>

                <div class="cc-eyebrow" style="margin-top: 18px">By quarter of the event date</div>
                @php $hasQ = collect($quarters)->sum('pct') > 0; @endphp
                @if (! $hasQ)
                    <div class="cc-muted-empty">No paid tickets on events held in the last six quarters.</div>
                @else
                    <svg class="cc-chart" viewBox="0 0 {{ count($quarters) * ($qW + $qGap) }} {{ $qH + 44 }}" role="img" aria-label="Takings by quarter">
                        <line class="base" x1="0" x2="{{ count($quarters) * ($qW + $qGap) }}" y1="{{ $qH + 18 }}" y2="{{ $qH + 18 }}"/>
                        @foreach ($quarters as $i => $q)
                            @php
                                $x = $i * ($qW + $qGap) + $qGap / 2;
                                $bh = $q['pct'] > 0 ? max(4, $q['pct'] / 100 * $qH) : 0;
                                $last = $i === count($quarters) - 1;
                            @endphp
                            <g class="col">
                                @if ($bh > 0)
                                    <rect class="bar {{ $last ? 'now' : '' }}" x="{{ $x }}" y="{{ $qH + 18 - $bh }}" width="{{ $qW }}" height="{{ $bh }}" rx="5"/>
                                    <text x="{{ $x + $qW / 2 }}" y="{{ $qH + 12 - $bh }}" text-anchor="middle" style="fill: var(--cc-ink); font-weight: 650">{{ $q['short'] }}</text>
                                @endif
                                <text x="{{ $x + $qW / 2 }}" y="{{ $qH + 34 }}" text-anchor="middle">{{ $q['label'] }}</text>
                                <title>{{ $q['label'] }}: {{ $q['value'] }} across {{ $q['events'] }} {{ $q['events'] === 1 ? 'event' : 'events' }}</title>
                            </g>
                        @endforeach
                    </svg>
                @endif
            </section>

            <section class="cc-card cc-s4 cc-side" aria-label="By city">
                <div class="cc-card-h">
                    <div>
                        <h2 class="cc-card-t">By city</h2>
                        <div class="cc-card-sub">Past events · bar shows how full they got</div>
                    </div>
                </div>
                @forelse ($cities as $c)
                    <div style="padding: 9px 0; {{ $loop->first ? '' : 'border-top: 1px solid var(--cc-line-2);' }}">
                        <div style="display:flex; justify-content:space-between; gap:10px; font-size:13px">
                            <span style="font-weight:600">{{ $c['city'] }}</span>
                            <b class="cc-num">{{ $c['revenue'] }}</b>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px; margin-top:6px">
                            <span class="cc-meter" style="flex:1"><i style="width: {{ $c['fill'] ?? 0 }}%"></i></span>
                            <span style="font-size:11.5px; color: var(--cc-ink-3); white-space:nowrap">{{ $c['events'] }} {{ $c['events'] === 1 ? 'event' : 'events' }} · {{ is_null($c['fill']) ? 'no seat limit' : $c['fill'].'% full' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="cc-muted-empty">No events have been held yet.</div>
                @endforelse
            </section>
        </div>
    </div>

    {{-- Interactive Historical Ledger --}}
    {{ $this->table }}
</x-filament-panels::page>
