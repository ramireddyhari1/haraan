<x-filament-panels::page>
    @php
        $ranges = ['today' => 'Today', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days', 'all' => 'All time'];
        $h = $hero;
        $catTones = ['var(--cc-blue)', 'var(--cc-ink)', 'var(--cc-amber)', 'var(--cc-green)', 'var(--cc-ink-3)'];
        $seatsPerRow = 20;
    @endphp

    @include('filament.partials.cc-styles')

    <style>
        /* Seat rows: an event's sell-through drawn as the seats themselves. */
        .ev-sale { display: grid; grid-template-columns: 44px minmax(0,1fr) auto; gap: 14px; align-items: center; padding: 11px 8px; border-radius: 12px; transition: background .12s, transform .08s; }
        .ev-sale + .ev-sale { border-top: 1px solid var(--cc-line-2); }
        .ev-sale:hover { background: var(--cc-sunk); }
        .ev-sale:active { transform: translateY(1px); }
        .ev-sale .t { font-size: 13.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ev-sale .s { font-size: 12px; color: var(--cc-ink-3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 6px; }
        .ev-seats { display: block; width: 100%; max-width: 300px; height: auto; }
        .ev-fig { text-align: right; min-width: 64px; }
        .ev-fig b { display: block; font-size: 15px; font-weight: 700; }
        .ev-fig span { font-size: 11.5px; color: var(--cc-ink-3); }
        .ev-sold { color: var(--cc-green); font-size: 11.5px; font-weight: 700; }
        .ev-soon { color: var(--cc-amber); }
    </style>

    <div class="cc" wire:poll.30s.visible="build">
        <div class="cc-bar">
            <div class="cc-today">
                <span class="cc-dot" aria-hidden="true"></span>
                <span><b class="cc-num">{{ $h['onSaleCount'] }}</b> {{ $h['onSaleCount'] === 1 ? 'event is' : 'events are' }} on sale · <b class="cc-num">{{ number_format($h['tickets']) }}</b> tickets sold {{ strtolower($h['rangeLabel']) }}</span>
            </div>
            <div class="cc-tools">
                <div class="cc-seg" role="group" aria-label="Date range">
                    @foreach ($ranges as $k => $lbl)
                        <button type="button" wire:click="setRange('{{ $k }}')" aria-pressed="{{ $range === $k ? 'true' : 'false' }}">{{ $lbl }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Takings + watch list --}}
        <div class="cc-grid">
            <section class="cc-card cc-s8" aria-label="Ticket takings">
                <div class="cc-money-top">
                    <div>
                        <div class="cc-eyebrow">Ticket takings · {{ $h['rangeLabel'] }}</div>
                        <div class="cc-gmv cc-num">{{ $h['revenue'] }}</div>
                    </div>
                    <div class="cc-facts">
                        <div class="cc-fact"><span>Tickets</span><b class="cc-num">{{ number_format($h['tickets']) }}</b></div>
                        <div class="cc-fact"><span>Orders</span><b class="cc-num">{{ number_format($h['orders']) }}</b></div>
                        <div class="cc-fact"><span>Avg ticket</span><b class="cc-num">{{ $h['avgTicket'] }}</b></div>
                        <div class="cc-fact" title="{{ $h['showUpNote'] }}"><span>Showed up</span><b class="cc-num">{{ $h['showUp'] }}</b></div>
                    </div>
                </div>
                @include('filament.partials.cc-bar-chart', ['series' => $series, 'emptyText' => 'No tickets sold '.($range === 'today' ? 'yet today' : 'in this window').'.'])
            </section>

            <section class="cc-card cc-s4 cc-side" aria-label="Keep an eye on">
                <div class="cc-card-h">
                    <div>
                        <h2 class="cc-card-t">Keep an eye on</h2>
                        <div class="cc-card-sub">Upcoming events only</div>
                    </div>
                </div>
                <div class="cc-rows">
                    @foreach ($watch as $w)
                        <a href="{{ $w['url'] }}" class="cc-row {{ $w['count'] > 0 && empty($w['good']) ? 'hot' : '' }}">
                            <span class="cc-row-ic"><x-filament::icon :icon="$w['icon']" /></span>
                            <span class="cc-row-main">
                                <span class="cc-row-t" style="display:block">{{ $w['title'] }}</span>
                                <span class="cc-row-s" style="display:block">{{ $w['sub'] }}</span>
                            </span>
                            <span class="cc-count cc-num {{ $w['count'] > 0 ? '' : 'zero' }}" @if(!empty($w['good']) && $w['count'] > 0) style="background: var(--cc-green-soft); color: var(--cc-green)" @endif>{{ $w['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- On sale (seat rows) + categories/cities --}}
        <div class="cc-grid">
            <section class="cc-card cc-s7" aria-label="On sale now">
                <div class="cc-card-h">
                    <div>
                        <h2 class="cc-card-t">On sale now</h2>
                        <div class="cc-card-sub">Soonest six · seats fill as tickets sell</div>
                    </div>
                    <a class="cc-more" href="{{ \App\Filament\Resources\Events\EventResource::getUrl('index') }}">All events<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4.5 2.5 8 6l-3.5 3.5"/></svg></a>
                </div>

                @if (empty($onSale))
                    <div class="cc-muted-empty">
                        <svg width="70" height="44" viewBox="0 0 70 44" fill="none" aria-hidden="true">
                            @for ($i = 0; $i < 6; $i++)
                                <path d="M{{ 4 + $i * 11 }} 30v-9a4 4 0 0 1 4-4h1a4 4 0 0 1 4 4v9" stroke="var(--cc-ink-3)" stroke-width="1.5"/>
                                <path d="M{{ 2 + $i * 11 }} 30h13" stroke="var(--cc-ink-3)" stroke-width="1.5" stroke-linecap="round"/>
                            @endfor
                        </svg>
                        Nothing is on sale. Published events with a future date show up here.
                    </div>
                @else
                    <div style="display: flex; flex-direction: column; margin: 0 -8px">
                        @foreach ($onSale as $e)
                            @php
                                $filled = $e['cap'] > 0 ? (int) round($e['sold'] / $e['cap'] * $seatsPerRow) : 0;
                                if ($e['cap'] > 0 && $e['sold'] > 0) { $filled = max(1, $filled); }
                                if ($e['soldOut']) { $filled = $seatsPerRow; }
                                [$mon, $day] = $e['when'] !== 'Date not set' ? [strtoupper(\Illuminate\Support\Carbon::parse($e['when'])->format('M')), \Illuminate\Support\Carbon::parse($e['when'])->format('j')] : ['—', '?'];
                            @endphp
                            <a class="ev-sale" href="{{ $e['url'] }}">
                                {{-- A tear-off calendar tab for the date --}}
                                <svg width="44" height="48" viewBox="0 0 44 48" aria-label="{{ $e['when'] }}">
                                    <rect x="1" y="5" width="42" height="42" rx="8" fill="var(--cc-surface)" stroke="var(--cc-ink)" stroke-width="1.4"/>
                                    <path d="M1 13a8 8 0 0 1 8-8h26a8 8 0 0 1 8 8v4H1z" fill="{{ ($e['days'] ?? 99) <= 2 ? 'var(--cc-amber)' : 'var(--cc-blue)' }}"/>
                                    <path d="M12 2v7M32 2v7" stroke="var(--cc-ink)" stroke-width="1.8" stroke-linecap="round"/>
                                    <text x="22" y="15" text-anchor="middle" style="fill:#fff; font-size: 8px; font-weight: 700; letter-spacing: .08em">{{ $mon }}</text>
                                    <text x="22" y="39" text-anchor="middle" class="cc-num" style="fill: var(--cc-ink); font-size: 17px; font-weight: 700">{{ $day }}</text>
                                </svg>
                                <span style="min-width:0">
                                    <span class="t" style="display:block">{{ $e['title'] }}</span>
                                    <span class="s" style="display:block">
                                        @if (! is_null($e['days']))
                                            <span class="{{ $e['days'] <= 2 ? 'ev-soon' : '' }}">{{ $e['days'] === 0 ? 'Today' : ($e['days'] === 1 ? 'Tomorrow' : 'In '.$e['days'].' days') }}</span>
                                        @endif
                                        @if ($e['where']) · {{ $e['where'] }}@endif
                                    </span>
                                    @if ($e['cap'] > 0 || $e['soldOut'])
                                        <svg class="ev-seats" viewBox="0 0 {{ $seatsPerRow * 15 }} 16" aria-label="{{ $e['sold'] }} of {{ $e['cap'] }} seats sold">
                                            @for ($i = 0; $i < $seatsPerRow; $i++)
                                                @php $x = $i * 15 + 1; $on = $i < $filled; @endphp
                                                <path d="M{{ $x }} 14V7a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v7z"
                                                      fill="{{ $on ? 'var(--cc-blue)' : 'none' }}" stroke="{{ $on ? 'var(--cc-blue)' : 'var(--cc-line)' }}" stroke-width="1.3"/>
                                            @endfor
                                        </svg>
                                    @else
                                        <span style="font-size:12px; color: var(--cc-ink-3)">No seat limit set</span>
                                    @endif
                                </span>
                                <span class="ev-fig">
                                    @if ($e['soldOut'])
                                        <span class="ev-sold">SOLD OUT</span>
                                    @elseif ($e['cap'] > 0)
                                        <b class="cc-num">{{ $e['pct'] }}%</b>
                                        <span class="cc-num">{{ number_format($e['sold']) }}/{{ number_format($e['cap']) }}</span>
                                    @else
                                        <b class="cc-num">{{ number_format($e['sold']) }}</b><span>sold</span>
                                    @endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="cc-s5 cc-stack">
                <section class="cc-card" aria-label="By category">
                    <div class="cc-card-h">
                        <div>
                            <h2 class="cc-card-t">By category</h2>
                            <div class="cc-card-sub">Share of ticket takings · {{ strtolower($h['rangeLabel']) }}</div>
                        </div>
                    </div>
                    @if (empty($categories))
                        <div class="cc-muted-empty">No tickets sold in this window.</div>
                    @else
                        <div class="cc-split-bar" role="img" aria-label="Takings by category">
                            @foreach ($categories as $i => $c)
                                <i style="width: {{ max(0.5, $c['share']) }}%; background: {{ $catTones[$i % 5] }}" title="{{ $c['label'] }} {{ $c['value'] }}"></i>
                            @endforeach
                        </div>
                        <div class="cc-split-legend" style="flex-direction: column; gap: 8px">
                            @foreach ($categories as $i => $c)
                                <span style="justify-content: space-between; width: 100%"><span><i style="background: {{ $catTones[$i % 5] }}"></i> {{ $c['label'] }}</span><span><b class="cc-num">{{ $c['value'] }}</b> <span style="color: var(--cc-ink-3)">{{ $c['share'] }}%</span></span></span>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="cc-card" aria-label="By city">
                    <div class="cc-card-h">
                        <div>
                            <h2 class="cc-card-t">By city</h2>
                            <div class="cc-card-sub">Where the event takes place</div>
                        </div>
                    </div>
                    @forelse ($cities as $c)
                        <div class="cc-city" title="{{ number_format($c['tickets']) }} tickets">
                            <span class="name">{{ $c['label'] }}</span>
                            <span class="track"><i style="width: {{ max(2, $c['bar']) }}%"></i></span>
                            <span class="v cc-num">{{ $c['value'] }}</span>
                        </div>
                    @empty
                        <div class="cc-muted-empty">No tickets sold in this window.</div>
                    @endforelse
                </section>
            </div>
        </div>

        {{-- Orders + venues --}}
        <div class="cc-grid">
            <section class="cc-card cc-s7" aria-label="Latest ticket orders">
                <div class="cc-card-h">
                    <div>
                        <h2 class="cc-card-t">Latest ticket orders</h2>
                        <div class="cc-card-sub">Every status, newest first</div>
                    </div>
                    <a class="cc-more" href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('index') }}">All bookings<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4.5 2.5 8 6l-3.5 3.5"/></svg></a>
                </div>
                @if (empty($orders))
                    <div class="cc-muted-empty">
                        <svg width="46" height="56" viewBox="0 0 46 56" fill="none" aria-hidden="true">
                            <path d="M5 3h36v47l-6-4-6 4-6-4-6 4-6-4-6 4z" fill="var(--cc-surface)" stroke="var(--cc-ink-3)" stroke-width="1.6" stroke-linejoin="round"/>
                            <path d="M12 14h22M12 22h16M12 30h19" stroke="var(--cc-line)" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                        No ticket orders yet.
                    </div>
                @else
                    <div class="cc-feed">
                        @foreach ($orders as $o)
                            <a href="{{ $o['url'] }}">
                                <span class="k">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6h15A1.5 1.5 0 0 1 21 7.5V10a2 2 0 0 0 0 4v2.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 16.5V14a2 2 0 0 0 0-4z"/><path d="M15 6.5v2M15 11v2M15 15.5v2" stroke-linecap="round"/></svg>
                                </span>
                                <span style="min-width:0">
                                    <span class="t" style="display:block">{{ $o['event'] }}</span>
                                    <span class="s" style="display:block">{{ $o['who'] }} · {{ $o['qty'] }} {{ $o['qty'] === 1 ? 'ticket' : 'tickets' }} · {{ $o['ago'] }}</span>
                                </span>
                                <span class="r">
                                    <span class="amt cc-num" style="display:block">{{ $o['amount'] }}</span>
                                    <span class="cc-pill {{ $o['tone'] }}"><i></i>{{ $o['status'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="cc-card cc-s5" aria-label="Top venues">
                <div class="cc-card-h">
                    <div>
                        <h2 class="cc-card-t">Top places</h2>
                        <div class="cc-card-sub">Event venues by ticket takings</div>
                    </div>
                </div>
                @forelse ($places as $p)
                    <div class="cc-city" style="grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) auto" title="{{ number_format($p['tickets']) }} tickets">
                        <span class="name">{{ $p['label'] }}</span>
                        <span class="track"><i style="width: {{ max(2, $p['bar']) }}%"></i></span>
                        <span class="v cc-num">{{ $p['value'] }}</span>
                    </div>
                @empty
                    <div class="cc-muted-empty">No tickets sold in this window.</div>
                @endforelse
            </section>
        </div>
    </div>
</x-filament-panels::page>
