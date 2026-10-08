<x-filament-panels::page>
    @php
        $ranges = ['today' => 'Today', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days', 'all' => 'All time'];
        $h = $hero;

        // ── Courts-today strip ─────────────────────────────────────────────
        $hrs = $venues['hours'] ?? [];
        $courtCount = max(0, (int) ($venues['courts'] ?? 0));
        $hourLabel = fn (int $hr) => $hr === 12 ? '12p' : ($hr > 12 ? ($hr - 12).'p' : $hr.'a');

        $splitTone = ['partners' => 'var(--cc-blue)', 'fees' => 'var(--cc-ink)', 'tax' => 'var(--cc-amber)'];
        $openCount = collect($radar)->where('count', '>', 0)->count();
    @endphp

    @include('filament.partials.cc-styles')

    <div class="cc"
         wire:poll.30s.visible="build"
         x-data="{ open: false, go() { this.open = true; this.$nextTick(() => this.$refs.q?.focus()) } }"
         @keydown.window.ctrl.k.prevent="go()"
         @keydown.window.meta.k.prevent="go()">

        {{-- ── Today line + tools ─────────────────────────────────────────── --}}
        <div class="cc-bar">
            <div class="cc-today">
                <span class="cc-dot" aria-hidden="true"></span>
                <span>Today <b class="cc-num">{{ $h['today'] }}</b> from <b class="cc-num">{{ $h['todayOrders'] }}</b> {{ $h['todayOrders'] === 1 ? 'booking' : 'bookings' }}</span>
                @if (! is_null($h['online']))
                    <span aria-hidden="true">·</span>
                    <span><b class="cc-num">{{ $h['online'] }}</b> {{ $h['online'] === 1 ? 'person' : 'people' }} in the app now</span>
                @endif
            </div>
            <div class="cc-tools">
                <button type="button" class="cc-search" @click="go()" aria-label="Search (Ctrl+K)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>
                    <span class="l">Find an event, venue, person, booking…</span>
                    <span class="cc-key">Ctrl K</span>
                </button>
                <div class="cc-seg" role="group" aria-label="Date range">
                    @foreach ($ranges as $k => $lbl)
                        <button type="button" wire:click="setRange('{{ $k }}')" aria-pressed="{{ $range === $k ? 'true' : 'false' }}">{{ $lbl }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Money + attention ──────────────────────────────────────────── --}}
        <div class="cc-grid">
            <section class="cc-card cc-s8" aria-label="Paid bookings">
                <div class="cc-money-top">
                    <div>
                        <div class="cc-eyebrow">Paid bookings · {{ $h['rangeLabel'] }}</div>
                        <div class="cc-gmv cc-num">{{ $h['gmv'] }}</div>
                        @if ($h['delta'])
                            <span class="cc-delta {{ $h['delta']['dir'] }}">
                                @if ($h['delta']['dir'] === 'up')
                                    <svg width="12" height="12" viewBox="0 0 12 12"><path d="M6 2.5 10 8H2z" fill="currentColor"/></svg>
                                @elseif ($h['delta']['dir'] === 'down')
                                    <svg width="12" height="12" viewBox="0 0 12 12"><path d="M6 9.5 2 4h8z" fill="currentColor"/></svg>
                                @endif
                                {{ $h['delta']['label'] }}
                            </span>
                        @endif
                    </div>
                    <div class="cc-facts">
                        <div class="cc-fact"><span>Orders</span><b class="cc-num">{{ number_format($h['orders']) }}</b></div>
                        <div class="cc-fact"><span>Average order</span><b class="cc-num">{{ $h['avg'] }}</b></div>
                        <div class="cc-fact"><span>Refunded</span><b class="cc-num" @if($h['refundsRaw'] > 0) style="color: var(--cc-red)" @endif>{{ $h['refunds'] }}</b></div>
                    </div>
                </div>

                @include('filament.partials.cc-bar-chart', ['series' => $series, 'emptyText' => 'No paid bookings '.($range === 'today' ? 'yet today' : 'in this window').'.'])

                @if (! empty($split))
                    <div class="cc-split">
                        <div class="cc-eyebrow" style="margin-bottom: 8px">Where it went</div>
                        <div class="cc-split-bar" role="img" aria-label="Split of paid bookings">
                            @foreach ($split as $p)
                                <i style="width: {{ max(0.5, $p['pct']) }}%; background: {{ $splitTone[$p['key']] }}" title="{{ $p['label'] }} {{ $p['fmt'] }}"></i>
                            @endforeach
                        </div>
                        <div class="cc-split-legend">
                            @foreach ($split as $p)
                                <span title="{{ $p['note'] }}"><i style="background: {{ $splitTone[$p['key']] }}"></i>{{ $p['label'] }} <b class="cc-num">{{ $p['fmt'] }}</b> <span style="color: var(--cc-ink-3)">{{ $p['pct'] }}%</span></span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

            <section class="cc-card cc-s4 cc-side" aria-label="Needs a person">
                <div class="cc-card-h">
                    <div>
                        <h2 class="cc-card-t">Needs a person</h2>
                        <div class="cc-card-sub">{{ $openCount === 0 ? 'Nothing is waiting on you' : $openCount.' of '.count($radar).' have something waiting' }}</div>
                    </div>
                </div>

                @if ($openCount === 0)
                    <div class="cc-clear">
                        {{-- Clipboard, every line ticked --}}
                        <svg width="64" height="72" viewBox="0 0 64 72" fill="none" aria-hidden="true">
                            <rect x="6" y="8" width="52" height="60" rx="8" fill="var(--cc-surface)" stroke="var(--cc-ink)" stroke-width="2"/>
                            <rect x="20" y="3" width="24" height="11" rx="4" fill="var(--cc-sunk)" stroke="var(--cc-ink)" stroke-width="2"/>
                            <path d="M16 30l3.5 3.5L26 27" stroke="var(--cc-green)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M31 30.5h17" stroke="var(--cc-line)" stroke-width="3" stroke-linecap="round"/>
                            <path d="M16 44l3.5 3.5L26 41" stroke="var(--cc-green)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M31 44.5h12" stroke="var(--cc-line)" stroke-width="3" stroke-linecap="round"/>
                            <path d="M16 58l3.5 3.5L26 55" stroke="var(--cc-green)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M31 58.5h15" stroke="var(--cc-line)" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                        <div>
                            <p>All clear</p>
                            <span>No support waiting, payouts due or failed payments.</span>
                        </div>
                    </div>
                @endif

                <div class="cc-rows">
                    @foreach ($radar as $item)
                        <a href="{{ $item['url'] }}" class="cc-row {{ $item['count'] > 0 ? 'hot' : '' }}">
                            <span class="cc-row-ic"><x-filament::icon :icon="$item['icon']" /></span>
                            <span class="cc-row-main">
                                <span class="cc-row-t" style="display:block">{{ $item['title'] }}</span>
                                <span class="cc-row-s" style="display:block">{{ $item['sub'] }}</span>
                            </span>
                            <span class="cc-count cc-num {{ $item['count'] > 0 ? '' : 'zero' }}">{{ $item['count'] > 0 ? $item['count'] : '0' }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- ── Lines of business: each drawing is a picture of its own number ── --}}
        <div class="cc-grid">
            {{-- Events — a ticket with the window's sales printed on it --}}
            <section class="cc-card cc-s4 cc-line-card" aria-label="Events">
                <div class="cc-card-h" style="margin-bottom: 0">
                    <h2 class="cc-card-t">
                        <span class="cc-glyph">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6h15A1.5 1.5 0 0 1 21 7.5V10a2 2 0 0 0 0 4v2.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 16.5V14a2 2 0 0 0 0-4z"/><path d="M15 6.5v2M15 11v2M15 15.5v2" stroke-linecap="round"/></svg>
                        </span>
                        Events
                    </h2>
                    <a class="cc-more" href="{{ $events['url'] }}">Open<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4.5 2.5 8 6l-3.5 3.5"/></svg></a>
                </div>

                <svg class="cc-figure" viewBox="0 0 300 118" aria-label="{{ number_format($events['tickets']) }} tickets sold">
                    <defs>
                        <clipPath id="cc-stub"><rect x="214" y="0" width="86" height="118"/></clipPath>
                    </defs>
                    <path d="M14 1H206a8 8 0 0 0 16 0H286a13 13 0 0 1 13 13V104a13 13 0 0 1-13 13H222a8 8 0 0 0-16 0H14A13 13 0 0 1 1 104V14A13 13 0 0 1 14 1Z"
                          fill="var(--cc-surface)" stroke="var(--cc-ink)" stroke-width="1.5"/>
                    <path d="M14 1H206a8 8 0 0 0 16 0H286a13 13 0 0 1 13 13V104a13 13 0 0 1-13 13H222a8 8 0 0 0-16 0H14A13 13 0 0 1 1 104V14A13 13 0 0 1 14 1Z"
                          fill="var(--cc-blue-soft)" clip-path="url(#cc-stub)"/>
                    <path d="M214 12V106" stroke="var(--cc-ink-3)" stroke-width="1.5" stroke-dasharray="1 5" stroke-linecap="round"/>
                    <text x="20" y="30" style="fill: var(--cc-ink-3); font-size: 11px; font-weight: 600">TICKETS SOLD · {{ strtoupper($h['rangeLabel']) }}</text>
                    <text x="18" y="78" class="cc-num" style="fill: var(--cc-ink); font-size: 44px; font-weight: 700; letter-spacing: -0.04em">{{ number_format($events['tickets']) }}</text>
                    <text x="20" y="100" style="fill: var(--cc-ink-2); font-size: 12px">{{ $events['gmv'] }} from {{ number_format($events['orders']) }} {{ $events['orders'] === 1 ? 'order' : 'orders' }}</text>
                    {{-- stub: the count of upcoming events, set like a seat number --}}
                    <text x="257" y="36" text-anchor="middle" style="fill: var(--cc-blue); font-size: 10px; font-weight: 700; letter-spacing: .12em">UPCOMING</text>
                    <text x="257" y="74" text-anchor="middle" class="cc-num" style="fill: var(--cc-ink); font-size: 30px; font-weight: 700">{{ $events['upcoming'] }}</text>
                    <text x="257" y="94" text-anchor="middle" style="fill: var(--cc-ink-3); font-size: 10.5px">{{ $events['upcoming'] === 1 ? 'event' : 'events' }}</text>
                </svg>

                @if ($events['next'])
                    <a class="cc-note" href="{{ $events['next']['url'] }}">
                        <span style="flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis">Next up <b>{{ $events['next']['title'] }}</b> · {{ $events['next']['when'] }}</span>
                        @if (! is_null($events['next']['sold']))
                            <span class="cc-meter" title="{{ $events['next']['sold'] }} of {{ $events['next']['total'] }} sold"><i style="width: {{ min(100, round($events['next']['sold'] / max(1, $events['next']['total']) * 100)) }}%"></i></span>
                            <span class="cc-num" style="font-size:12px">{{ $events['next']['sold'] }}/{{ $events['next']['total'] }}</span>
                        @endif
                    </a>
                @else
                    <div class="cc-note">No upcoming events are listed.</div>
                @endif
            </section>

            {{-- Venues — today's court-hours, one cell per hour --}}
            <section class="cc-card cc-s4 cc-line-card" aria-label="Venues">
                <div class="cc-card-h" style="margin-bottom: 0">
                    <h2 class="cc-card-t">
                        <span class="cc-glyph">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="M12 5v14"/><circle cx="12" cy="12" r="2.6"/><path d="M3 9h3v6H3M21 9h-3v6h3"/></svg>
                        </span>
                        Venues
                    </h2>
                    <a class="cc-more" href="{{ $venues['url'] }}">Open<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4.5 2.5 8 6l-3.5 3.5"/></svg></a>
                </div>

                @php
                    $cells = count($hrs);
                    $gw = 300; $gx = 0; $cellW = $cells ? ($gw - ($cells - 1) * 3) / $cells : 0;
                    $nowH = (int) ($venues['nowHour'] ?? 0);
                @endphp
                <div>
                    <div style="display:flex; align-items:baseline; justify-content:space-between; gap:10px">
                        <div>
                            <span class="cc-num" style="font-size: 30px; font-weight: 700; letter-spacing: -0.03em">{{ $venues['bookedHours'] }}</span>
                            <span style="font-size: 13px; color: var(--cc-ink-2)">court-{{ $venues['bookedHours'] === 1 ? 'hour' : 'hours' }} booked today</span>
                        </div>
                        @if ($courtCount > 0)
                            <span style="font-size: 12px; color: var(--cc-ink-3)">{{ number_format($venues['openHours']) }} open</span>
                        @endif
                    </div>
                    <svg class="cc-figure wide" viewBox="0 0 300 66" style="margin-top: 10px" aria-label="Court bookings by hour today">
                        @foreach ($hrs as $hr => $cnt)
                            @php
                                $i = $loop->index;
                                $x = $gx + $i * ($cellW + 3);
                                $ratio = $courtCount > 0 ? min(1, $cnt / $courtCount) : ($cnt > 0 ? 1 : 0);
                                $past = $hr < $nowH;
                            @endphp
                            <g>
                                @if ($cnt > 0)
                                    <rect x="{{ $x }}" y="14" width="{{ $cellW }}" height="30" rx="4" fill="var(--cc-blue)" opacity="{{ $past ? 0.35 + 0.4 * $ratio : 0.45 + 0.55 * $ratio }}"/>
                                @else
                                    <rect x="{{ $x + 0.5 }}" y="14.5" width="{{ $cellW - 1 }}" height="29" rx="4" fill="{{ $past ? 'var(--cc-line-2)' : 'none' }}" stroke="var(--cc-line)" stroke-dasharray="{{ $past ? '0' : '3 2' }}"/>
                                @endif
                                <title>{{ $hourLabel($hr) }}–{{ $hourLabel($hr + 1) }}: {{ $cnt }} {{ $cnt === 1 ? 'court' : 'courts' }} booked{{ $courtCount ? ' of '.$courtCount : '' }}</title>
                                @if ($hr === $nowH)
                                    <path d="M{{ $x + $cellW / 2 - 4 }},4 h8 l-4,6 z" fill="var(--cc-ink)"/>
                                @endif
                                @if ($hr % 3 === 0)
                                    <text x="{{ $x + $cellW / 2 }}" y="60" text-anchor="middle" style="fill: var(--cc-ink-3); font-size: 10px">{{ $hourLabel($hr) }}</text>
                                @endif
                            </g>
                        @endforeach
                    </svg>
                </div>

                <div class="cc-kv">
                    <div><span>Takings</span><b class="cc-num">{{ $venues['gmv'] }}</b></div>
                    <div><span>Bookings</span><b class="cc-num">{{ number_format($venues['bookings']) }}</b></div>
                    <div><span>Venues · courts</span><b class="cc-num">{{ $venues['venues'] }} · {{ $courtCount }}</b></div>
                </div>
            </section>

            {{-- Memberships — one card per paid plan, fanned, each carrying its member count --}}
            <section class="cc-card cc-s4 cc-line-card" aria-label="Memberships">
                <div class="cc-card-h" style="margin-bottom: 0">
                    <h2 class="cc-card-t">
                        <span class="cc-glyph">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12.5" rx="2"/><path d="M3 10h18"/><path d="M6.5 15h4" stroke-linecap="round"/></svg>
                        </span>
                        Memberships
                    </h2>
                    @if ($members['url'])
                        <a class="cc-more" href="{{ $members['url'] }}">Open<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4.5 2.5 8 6l-3.5 3.5"/></svg></a>
                    @endif
                </div>

                @php
                    $plans = $members['plans'];
                    $pc = count($plans);
                    $angles = match ($pc) { 1 => [0], 2 => [-6, 6], default => [-10, 0, 10] };
                    $offs = match ($pc) { 1 => [0], 2 => [-48, 48], default => [-86, 0, 86] };
                @endphp
                <svg class="cc-figure" viewBox="0 0 300 128" aria-label="Active members by plan">
                    @if ($pc === 0)
                        <rect x="90" y="20" width="120" height="78" rx="10" fill="none" stroke="var(--cc-line)" stroke-width="1.5" stroke-dasharray="4 3"/>
                        <text x="150" y="64" text-anchor="middle" style="fill: var(--cc-ink-3); font-size: 12px">No paid plans yet</text>
                    @else
                        @foreach ($plans as $i => $plan)
                            @php
                                $dark = $i === $pc - 1 && $pc > 1;   // the top plan is the dark card
                                $cxp = 150 + $offs[$i];
                                $fill = $dark ? '#0d1424' : ($i === $pc - 2 || $pc === 1 ? 'var(--cc-blue)' : 'var(--cc-surface)');
                                $ink = ($dark || $fill === 'var(--cc-blue)') ? '#ffffff' : 'var(--cc-ink)';
                                $sub = ($dark || $fill === 'var(--cc-blue)') ? 'rgba(255,255,255,.72)' : 'var(--cc-ink-3)';
                            @endphp
                            <g transform="rotate({{ $angles[$i] }} {{ $cxp }} 150)">
                                <rect x="{{ $cxp - 58 }}" y="22" width="116" height="78" rx="10" fill="{{ $fill }}" stroke="{{ $fill === 'var(--cc-surface)' ? 'var(--cc-ink)' : 'none' }}" stroke-width="1.4"
                                      style="filter: drop-shadow(0 4px 6px rgba(16,24,40,.12))"/>
                                @if ($dark)
                                    <rect x="{{ $cxp - 54 }}" y="26" width="108" height="70" rx="7" fill="none" stroke="var(--cc-gold)" stroke-width=".8" opacity=".8"/>
                                @endif
                                <rect x="{{ $cxp - 46 }}" y="34" width="14" height="10" rx="2.5" fill="none" stroke="{{ $sub }}" stroke-width="1"/>
                                <text x="{{ $cxp + 46 }}" y="43" text-anchor="end" style="fill: {{ $sub }}; font-size: 10px; font-weight: 700; letter-spacing: .1em">{{ strtoupper(\Illuminate\Support\Str::limit($plan['name'], 10, '')) }}</text>
                                <text x="{{ $cxp - 46 }}" y="80" class="cc-num" style="fill: {{ $ink }}; font-size: 24px; font-weight: 700">{{ number_format($plan['active']) }}</text>
                                <text x="{{ $cxp - 46 }}" y="92" style="fill: {{ $sub }}; font-size: 9.5px">active</text>
                            </g>
                        @endforeach
                    @endif
                </svg>

                <div class="cc-kv">
                    <div><span>Paying members</span><b class="cc-num">{{ number_format($members['active']) }}</b></div>
                    <div><span>Paid · {{ strtolower($h['rangeLabel']) }}</span><b class="cc-num">{{ $members['revenue'] }}</b></div>
                </div>
            </section>
        </div>

        {{-- ── Activity (left) and money/where/systems (right) ──────────── --}}
        <div class="cc-grid">
            <div class="cc-s7 cc-stack">
            <section class="cc-card" aria-label="Latest bookings">
                <div class="cc-card-h">
                    <div>
                        <h2 class="cc-card-t">Latest bookings</h2>
                        <div class="cc-card-sub">Every status, newest first</div>
                    </div>
                    <a class="cc-more" href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('index') }}">All bookings<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4.5 2.5 8 6l-3.5 3.5"/></svg></a>
                </div>

                @if (empty($feed))
                    <div class="cc-muted-empty">
                        <svg width="46" height="56" viewBox="0 0 46 56" fill="none" aria-hidden="true">
                            <path d="M5 3h36v47l-6-4-6 4-6-4-6 4-6-4-6 4z" fill="var(--cc-surface)" stroke="var(--cc-ink-3)" stroke-width="1.6" stroke-linejoin="round"/>
                            <path d="M12 14h22M12 22h16M12 30h19" stroke="var(--cc-line)" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                        No bookings have been made yet.
                    </div>
                @else
                    <div class="cc-feed">
                        @foreach ($feed as $f)
                            <a href="{{ $f['url'] }}" title="{{ $f['at'] }}">
                                <span class="k">
                                    @if ($f['kind'] === 'venue')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="M12 5v14"/><circle cx="12" cy="12" r="2.6"/></svg>
                                    @else
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6h15A1.5 1.5 0 0 1 21 7.5V10a2 2 0 0 0 0 4v2.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 16.5V14a2 2 0 0 0 0-4z"/><path d="M15 6.5v2M15 11v2M15 15.5v2" stroke-linecap="round"/></svg>
                                    @endif
                                </span>
                                <span style="min-width:0">
                                    <span class="t" style="display:block">{{ $f['what'] }}</span>
                                    <span class="s" style="display:block">{{ $f['who'] }}@if($f['detail']) · {{ $f['detail'] }}@endif · {{ $f['ago'] }}</span>
                                </span>
                                <span class="r">
                                    <span class="amt cc-num" style="display:block">{{ $f['amount'] }}</span>
                                    <span class="cc-pill {{ $f['status'] }}"><i></i>{{ $f['statusLabel'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
            <section class="cc-card" aria-label="Recent admin changes">
                <div class="cc-card-h">
                    <div>
                        <h2 class="cc-card-t">Recent admin changes</h2>
                        <div class="cc-card-sub">From the audit log</div>
                    </div>
                </div>
                @if (empty($audit))
                    <div class="cc-muted-empty">Nobody has changed anything yet.</div>
                @else
                    <div class="cc-tl">
                        @foreach ($audit as $a)
                            <div class="cc-tl-i">
                                <div class="cc-tl-t">{{ $a['what'] }}@if($a['subject']) <span style="color: var(--cc-ink-3); font-weight: 500">· {{ $a['subject'] }}</span>@endif</div>
                                <div class="cc-tl-s">{{ $a['who'] }} · {{ $a['day'] }}, {{ $a['time'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
            </div>
            <div class="cc-s5 cc-stack">
            <section class="cc-card" aria-label="Cities">
                <div class="cc-card-h">
                    <div>
                        <h2 class="cc-card-t">Where it sold</h2>
                        <div class="cc-card-sub">Paid bookings by city · {{ strtolower($h['rangeLabel']) }}</div>
                    </div>
                </div>

                @forelse ($cities as $c)
                    <div class="cc-city" title="{{ $c['orders'] }} {{ $c['orders'] === 1 ? 'booking' : 'bookings' }}">
                        <span class="name">{{ $c['city'] }}</span>
                        <span class="track"><i style="width: {{ max(2, $c['share']) }}%"></i></span>
                        <span class="v cc-num">{{ $c['gmv'] }}</span>
                    </div>
                @empty
                    <div class="cc-muted-empty">No paid bookings in this window.</div>
                @endforelse

                <a class="cc-owed" href="{{ $ledger['url'] }}">
                    <div>
                        <span>Still owed to partners</span>
                        <b class="cc-num" @if($ledger['owedRaw'] > 0) style="color: var(--cc-amber)" @endif>{{ $ledger['owed'] }}</b>
                    </div>
                    <div style="text-align:right">
                        <span>In a payout batch <b class="cc-num" style="font-size:13px; color: var(--cc-ink)">{{ $ledger['inFlight'] }}</b></span>
                        <span>Paid out today <b class="cc-num" style="font-size:13px; color: var(--cc-ink)">{{ $ledger['paidToday'] }}</b></span>
                    </div>
                </a>
            </section>
            <section class="cc-card" aria-label="Systems">
                <div class="cc-card-h">
                    <div>
                        <h2 class="cc-card-t">Systems</h2>
                        <div class="cc-card-sub">Checked when this page loaded</div>
                    </div>
                </div>
                @foreach ($systems as $s)
                    <div class="cc-sys">
                        <span class="n"><span class="cc-led {{ $s['ok'] ? '' : 'off' }}"></span>{{ $s['name'] }}</span>
                        <span class="d cc-num">{{ $s['detail'] }}</span>
                    </div>
                @endforeach
            </section>
            </div>
        </div>

        {{-- ── Ctrl/⌘ K ────────────────────────────────────────────────────── --}}
        <div class="cc-modal" x-show="open" x-cloak style="display:none"
             x-transition.opacity.duration.120ms
             @click.self="open = false" @keydown.escape.window="open = false">
            <div class="cc-modal-box" role="dialog" aria-label="Search">
                <div class="cc-modal-in">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--cc-ink-3)" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>
                    <input x-ref="q" type="text" wire:model.live.debounce.200ms="searchQuery"
                           placeholder="Event name, venue, person, phone, booking #…" autocomplete="off" />
                    <button type="button" class="cc-key" @click="open = false">Esc</button>
                </div>
                <div class="cc-res">
                    @if (mb_strlen(trim($searchQuery)) < 2)
                        <div class="cc-res-empty">Type at least two letters. Searches events, venues, people (name, email, phone) and bookings (ID, ticket code, payment ID).</div>
                    @elseif (empty($searchResults))
                        <div class="cc-res-empty">Nothing matches “{{ $searchQuery }}”.</div>
                    @else
                        @foreach (collect($searchResults)->groupBy('group') as $group => $rows)
                            <div class="cc-res-g">{{ $group }}</div>
                            @foreach ($rows as $r)
                                <a href="{{ $r['url'] }}">
                                    <span style="min-width:0">
                                        <span class="t" style="display:block">{{ $r['title'] }}</span>
                                        @if ($r['meta'])<span class="m" style="display:block">{{ $r['meta'] }}</span>@endif
                                    </span>
                                </a>
                            @endforeach
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
