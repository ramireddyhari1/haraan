@php
    $t = $this->getTelemetry();
    $money = static fn ($n): string => '₹' . number_format((float) $n);
    $peak = collect($t['days'] ?? [])->max('booked') ?: 0;
@endphp

<x-filament-widgets::widget>
    @if (($t['ready'] ?? false) === true)
        {{-- Self-contained styles: this blade isn't scanned by the compiled panel theme, so
             Tailwind responsive classes here (sm:grid-cols-7 …) never existed and the week
             strip stacked into seven full-width rows. --}}
        <style>
            .vn360 { --v-ink: #0f172a; --v-ink-2: #475569; --v-mute: #94a3b8; --v-line: #e8edf4; --v-sunk: #f6f8fc;
                --v-surface: #fff; --v-blue: #2563eb; --v-blue-soft: #e8efff; --v-good: #0f9d63; --v-warn: #b45309; --v-bad: #be123c;
                position: relative; overflow: hidden; background: var(--v-surface); border-radius: 18px;
                box-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 0 0 1px var(--v-line); padding: 20px 22px 22px;
                display: grid; gap: 18px; margin-bottom: 12px; }
            .dark .vn360 { --v-ink: #f1f5f9; --v-ink-2: #cbd5e1; --v-mute: #64748b; --v-line: #1f2937; --v-sunk: #0b1220;
                --v-surface: #111827; --v-blue: #6f9bff; --v-blue-soft: #16213a; --v-good: #34d399; --v-warn: #fbbf24; --v-bad: #fb7185; }

            .vn360-id { display: flex; gap: 16px; align-items: center; padding-bottom: 16px; border-bottom: 1px solid var(--v-line); }
            .vn360-art { width: 96px; flex: none; border-radius: 14px; padding: 8px; background: var(--v-blue-soft);
                --cc-ink: var(--v-ink-2); --cc-blue-soft: var(--v-surface); --cc-surface: var(--v-blue-soft); }
            .vn360-art .cb-pitch { width: 100%; height: auto; display: block; }
            .vn360-name { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; }
            .vn360-name h2 { margin: 0; font-size: 18px; font-weight: 720; letter-spacing: -.015em; color: var(--v-ink); }
            .vn360-sub { margin: 4px 0 0; font-size: 12.5px; color: var(--v-ink-2); line-height: 1.5; }
            .vn360-pill { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 3px 10px;
                font-size: 12px; font-weight: 650; border: 1px solid; }
            .vn360-pill i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
            .vn360-pill.on { color: var(--v-good); border-color: color-mix(in srgb, var(--v-good) 35%, transparent); background: color-mix(in srgb, var(--v-good) 9%, transparent); }
            .vn360-pill.on i { animation: vn360-pulse 1.8s ease-in-out infinite; }
            .vn360-pill.off { color: var(--v-warn); border-color: color-mix(in srgb, var(--v-warn) 35%, transparent); background: color-mix(in srgb, var(--v-warn) 9%, transparent); }
            @keyframes vn360-pulse { 50% { opacity: .35; } }
            @media (prefers-reduced-motion: reduce) { .vn360-pill.on i { animation: none; } }
            .vn360-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
            .vn360-chip { font-size: 11.5px; font-weight: 600; color: var(--v-ink-2); background: var(--v-sunk);
                border: 1px solid var(--v-line); border-radius: 999px; padding: 2px 9px; }

            .vn360-figs { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
            .vn360-fig { background: var(--v-sunk); border: 1px solid var(--v-line); border-radius: 12px; padding: 14px 15px; min-width: 0; }
            .vn360-fig dt { font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--v-mute); }
            .vn360-fig dd { margin: 6px 0 0; font-size: 23px; font-weight: 740; letter-spacing: -.03em; color: var(--v-ink); font-variant-numeric: tabular-nums; }
            .vn360-fig dd.good { color: var(--v-good); } .vn360-fig dd.warn { color: var(--v-warn); }
            .vn360-fig p { margin: 3px 0 0; font-size: 12px; color: var(--v-mute); line-height: 1.4; }
            .vn360-track { height: 5px; border-radius: 99px; background: var(--v-line); overflow: hidden; margin-top: 10px; }
            .vn360-track b { display: block; height: 100%; border-radius: 99px; background: var(--v-blue); }

            .vn360-wk-h { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
            .vn360-wk-h span:first-child { font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--v-mute); }
            .vn360-wk-h span:last-child { font-size: 12px; color: var(--v-mute); }
            .vn360-wk { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 8px; }
            .vn360-day { border: 1px solid var(--v-line); border-radius: 12px; padding: 10px 8px 9px; text-align: center; background: var(--v-surface); }
            .vn360-day.today { border-color: color-mix(in srgb, var(--v-blue) 45%, var(--v-line)); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--v-blue) 25%, transparent); }
            .vn360-day.closed { background: repeating-linear-gradient(135deg, var(--v-sunk) 0 6px, var(--v-surface) 6px 12px); }
            .vn360-dl { font-size: 12px; font-weight: 700; color: var(--v-ink); }
            .vn360-dd { font-size: 11px; color: var(--v-mute); }
            {{-- A short column per day: booked as a share of the busiest day, so the week has a shape. --}}
            .vn360-col { height: 38px; margin: 8px auto 6px; width: 18px; border-radius: 6px; background: var(--v-sunk);
                display: flex; align-items: flex-end; overflow: hidden; border: 1px solid var(--v-line); }
            .vn360-col b { display: block; width: 100%; background: var(--v-blue); border-radius: 4px 4px 0 0; }
            .vn360-n { font-size: 13px; font-weight: 720; color: var(--v-ink); font-variant-numeric: tabular-nums; }
            .vn360-n small { font-size: 11px; font-weight: 500; color: var(--v-mute); }
            .vn360-closed { font-size: 11px; font-weight: 650; color: var(--v-bad); margin-top: 4px; }

            .vn360-nophoto { display: flex; align-items: center; gap: 14px; border: 1px dashed color-mix(in srgb, var(--v-blue) 40%, var(--v-line));
                border-radius: 14px; padding: 10px 14px; background: color-mix(in srgb, var(--v-blue) 4%, var(--v-surface)); }
            .vn360-nophoto b { display: block; font-size: 13.5px; color: var(--v-ink); }
            .vn360-nophoto span { font-size: 12.5px; color: var(--v-ink-2); }

            @media (max-width: 1100px) { .vn360-figs { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @media (max-width: 640px) {
                .vn360 { padding: 16px; }
                .vn360-id { align-items: flex-start; }
                .vn360-art { width: 72px; }
                .vn360-wk { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            }
        </style>

        <div class="vn360">
            <div class="vn360-id">
                <div class="vn360-art">
                    @include('filament.venue.court-pitch', ['sport' => $t['mainSport'], 'kind' => null])
                </div>
                <div style="min-width: 0">
                    <div class="vn360-name">
                        <h2>{{ $t['name'] }}</h2>
                        <span class="vn360-pill {{ $t['onSale'] ? 'on' : 'off' }}"><i></i>{{ $t['statusLabel'] }}</span>
                    </div>
                    <p class="vn360-sub">
                        @if ($t['city']){{ $t['city'] }} · @endif
                        {{ $t['courtsActive'] }} of {{ $t['courtsTotal'] }} {{ \Illuminate\Support\Str::plural('court', $t['courtsTotal']) }} active
                        @if ($t['hoursToday'])
                            · open {{ $t['hoursToday']['open'] }}–{{ $t['hoursToday']['close'] }} today
                        @else
                            · closed today
                        @endif
                        @if ($t['setupLeft'] > 0)
                            · <strong>{{ $t['setupLeft'] }} setup {{ \Illuminate\Support\Str::plural('step', $t['setupLeft']) }} left</strong>
                        @endif
                    </p>
                    @if ($t['sports'] !== [])
                        <div class="vn360-chips">
                            @foreach ($t['sports'] as $sport)
                                <span class="vn360-chip">{{ $sport }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @unless ($t['hasPhoto'])
                <div class="vn360-nophoto">
                    <x-hrn-illo name="photo" :size="78" />
                    <div>
                        <b>No photos yet — customers can't see this venue without one</b>
                        <span>The first photo is the cover on the venue card. Add them in Edit → Photos.</span>
                    </div>
                </div>
            @endunless

            <dl class="vn360-figs" style="margin: 0">
                <div class="vn360-fig">
                    <dt>Occupancy today</dt>
                    <dd>{{ $t['occupancy'] }}%</dd>
                    <p>{{ $t['todayBookings'] }} booked of {{ $t['offeredToday'] }} court-hours offered</p>
                    <div class="vn360-track"><b style="width: {{ $t['occupancy'] }}%"></b></div>
                </div>
                <div class="vn360-fig">
                    <dt>Collected today</dt>
                    <dd @class(['good' => $t['collectedToday'] > 0])>{{ $money($t['collectedToday']) }}</dd>
                    <p>From the payment ledger, not the order total</p>
                </div>
                <div class="vn360-fig">
                    <dt>Collected · 30 days</dt>
                    <dd>{{ $money($t['collected30d']) }}</dd>
                    <p>Advances, desk payments and refunds netted</p>
                </div>
                <div class="vn360-fig">
                    <dt>Balance due</dt>
                    <dd @class(['warn' => $t['balanceDue'] > 0])>{{ $money($t['balanceDue']) }}</dd>
                    <p>
                        @if ($t['owingCount'] > 0)
                            Across {{ $t['owingCount'] }} upcoming {{ \Illuminate\Support\Str::plural('booking', $t['owingCount']) }}
                        @else
                            Nothing outstanding ahead
                        @endif
                    </p>
                </div>
            </dl>

            <div>
                <div class="vn360-wk-h"><span>Next seven days</span><span>booked / offered</span></div>
                <div class="vn360-wk">
                    @foreach ($t['days'] as $i => $day)
                        <div @class(['vn360-day', 'today' => $i === 0, 'closed' => $day['closed']])>
                            <div class="vn360-dl">{{ $day['label'] }}</div>
                            <div class="vn360-dd">{{ $day['date'] }}</div>
                            @if ($day['closed'])
                                <div class="vn360-col"></div>
                                <div class="vn360-closed">Closed</div>
                            @else
                                <div class="vn360-col" title="{{ $day['percent'] }}% of what was offered">
                                    <b style="height: {{ $peak > 0 ? max($day['booked'] > 0 ? 8 : 0, (int) round($day['booked'] / $peak * 100)) : 0 }}%"></b>
                                </div>
                                <div class="vn360-n">{{ $day['booked'] }}<small>/{{ $day['offered'] }}</small></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
