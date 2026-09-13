{{-- Messaging ledger, read side. Self-contained styling (.hmu-*): Filament's
     precompiled CSS bundle doesn't carry arbitrary Tailwind utilities used in
     custom views, so this page styles itself. Dark mode keys off html.dark. --}}
@php
    $totals = $this->getTotals();
    $partners = $this->getPartnerRows();
    $failures = $this->getRecentFailures();
    $reasons = $this->getFailureReasons();

    $rate = $totals['failureRate'];
    $attempted = $totals['sent'] + $totals['failed'];

    // Tone is driven by the delivery failure rate, because that's the number that
    // means customers aren't getting their tickets.
    $tone = $attempted === 0 ? 'idle' : ($rate >= 10 ? 'down' : ($rate >= 2 ? 'warn' : 'ok'));

    $hero = match ($tone) {
        'down' => ['grad' => 'linear-gradient(135deg,#ef4444,#dc2626)', 'head' => 'Delivery is failing',
                   'sub' => $totals['failed'] . ' of ' . $attempted . ' messages never reached anyone'],
        'warn' => ['grad' => 'linear-gradient(135deg,#f59e0b,#d97706)', 'head' => 'Some messages aren\'t landing',
                   'sub' => $rate . '% of sends failed this period'],
        'ok'   => ['grad' => 'linear-gradient(135deg,#064e3b 0%,#047857 50%,#10b981 100%)', 'head' => 'Delivery is healthy',
                   'sub' => number_format($totals['sent']) . ' messages delivered, ' . $rate . '% failed'],
        default => ['grad' => 'linear-gradient(135deg,#334155,#1e293b)', 'head' => 'Nothing sent yet',
                   'sub' => 'No messages recorded for ' . $this->periodLabel()],
    };

    $reasonLabel = [
        'failed' => 'Rejected by provider',
        'disabled' => 'Channel switched off',
        'unconfigured' => 'No credentials / sender',
        'unroutable' => 'Bad phone number',
    ];
@endphp

<x-filament-panels::page>
    <div class="hmu">

        {{-- ---------- Period switch ---------- --}}
        <div class="hmu-months">
            @foreach ([0 => 'This month', 1 => 'Last month', 2 => '2 months ago'] as $back => $label)
                <button type="button" wire:click="showMonth({{ $back }})"
                    class="hmu-month {{ $this->monthsBack === $back ? 'is-on' : '' }}">{{ $label }}</button>
            @endforeach
            <span class="hmu-period">{{ $this->periodLabel() }}</span>
        </div>

        {{-- ---------- Hero ---------- --}}
        <section class="hmu-hero" style="background:{{ $hero['grad'] }}">
            <div class="hmu-hero-in">
                <div class="hmu-hero-top">
                    <h2 class="hmu-h1">{{ $hero['head'] }}</h2>
                    <span class="hmu-beta">Beta</span>
                </div>
                <p class="hmu-hsub">{{ $hero['sub'] }}</p>
            </div>
        </section>

        {{-- ---------- Totals ---------- --}}
        <div class="hmu-kpis">
            <div class="hmu-kpi">
                <span class="hmu-kpi-lab">Conversations</span>
                <span class="hmu-kpi-val">{{ number_format($totals['conversations']) }}</span>
                <span class="hmu-kpi-sub">24h windows opened — the billable unit</span>
            </div>
            <div class="hmu-kpi">
                <span class="hmu-kpi-lab">Delivered</span>
                <span class="hmu-kpi-val">{{ number_format($totals['sent']) }}</span>
                <span class="hmu-kpi-sub">messages that reached the provider</span>
            </div>
            <div class="hmu-kpi">
                <span class="hmu-kpi-lab">Not delivered</span>
                <span class="hmu-kpi-val tone-{{ $totals['failed'] > 0 ? 'bad' : 'ok' }}">
                    {{ number_format($totals['failed']) }}
                </span>
                <span class="hmu-kpi-sub">including sends never attempted</span>
            </div>
            <div class="hmu-kpi">
                <span class="hmu-kpi-lab">Failure rate</span>
                <span class="hmu-kpi-val tone-{{ $tone === 'ok' || $tone === 'idle' ? 'ok' : 'bad' }}">{{ $rate }}%</span>
                <span class="hmu-kpi-sub">of everything attempted</span>
            </div>
        </div>

        {{-- ---------- Channel split + failure reasons ---------- --}}
        <div class="hmu-split">
            <section class="hmu-card">
                <h3 class="hmu-card-title">By channel</h3>
                @forelse ($totals['byChannel'] as $channel => $c)
                    <div class="hmu-row">
                        <span class="hmu-row-name">{{ ucfirst($channel) }}</span>
                        <span class="hmu-row-meta">
                            {{ number_format($c['sent']) }} sent
                            @if ($c['conversations'] > 0) · {{ number_format($c['conversations']) }} conversations @endif
                            @if ($c['failed'] > 0) · <span class="hmu-bad">{{ number_format($c['failed']) }} failed</span> @endif
                        </span>
                    </div>
                @empty
                    <p class="hmu-empty-line">No sends recorded this period.</p>
                @endforelse

                @php $in = $this->getInboundSummary(); @endphp
                <p class="hmu-skips" style="margin-top:10px;">
                    Inbound: {{ number_format($in['messages']) }}
                    {{ \Illuminate\Support\Str::plural('message', $in['messages']) }}
                    from {{ number_format($in['senders']) }}
                    {{ \Illuminate\Support\Str::plural('number', $in['senders']) }}
                    · {{ number_format($in['optOuts']) }} opted out
                </p>
            </section>

            <section class="hmu-card">
                <h3 class="hmu-card-title">Why sends failed</h3>
                @forelse ($reasons as $status => $count)
                    <div class="hmu-row">
                        <span class="hmu-row-name">{{ $reasonLabel[$status] ?? ucfirst($status) }}</span>
                        <span class="hmu-row-meta">{{ number_format($count) }}</span>
                    </div>
                @empty
                    <p class="hmu-empty-line">Nothing failed this period.</p>
                @endforelse
            </section>
        </div>

        {{-- ---------- Per-partner volumes ---------- --}}
        <section class="hmu-card">
            <h3 class="hmu-card-title">Volume by partner</h3>
            <p class="hmu-card-sub">What each partner sends — the basis for sizing plan quotas.</p>

            @if ($partners === [])
                <p class="hmu-empty-line">Nothing recorded yet for {{ $this->periodLabel() }}.</p>
            @else
                <div class="hmu-table-wrap">
                    <table class="hmu-table">
                        <thead>
                            <tr>
                                <th>Partner</th>
                                <th class="num">Conversations</th>
                                <th class="num">Delivered</th>
                                <th class="num">Failed</th>
                                <th class="num">Failure rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($partners as $p)
                                <tr>
                                    <td>
                                        {{ $p['name'] }}
                                        @if ($p['isPlatform'])
                                            <span class="hmu-tag">not billable</span>
                                        @endif
                                    </td>
                                    <td class="num">{{ number_format($p['conversations']) }}</td>
                                    <td class="num">{{ number_format($p['sent']) }}</td>
                                    <td class="num {{ $p['failed'] > 0 ? 'hmu-bad' : '' }}">{{ number_format($p['failed']) }}</td>
                                    <td class="num {{ $p['failureRate'] >= 10 ? 'hmu-bad' : '' }}">{{ $p['failureRate'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- ---------- Journey queue ---------- --}}
        @php $j = $this->getJourneys(); @endphp
        <section class="hmu-card">
            <h3 class="hmu-card-title">Journey queue</h3>
            <p class="hmu-card-sub">Event reminders and post-event review requests, queued ahead of time.</p>

            @unless ($j['enabled'])
                {{-- Without this the queue looks broken rather than deliberately paused. --}}
                <div class="hmu-note">
                    <strong>Journeys are switched off.</strong>
                    Messages are still being queued and held, but nothing is delivered.
                    Set <code>MESSAGING_JOURNEYS_ENABLED=true</code> to start sending.
                </div>
            @endunless

            <div class="hmu-chips">
                @foreach (['pending' => 'Queued', 'sent' => 'Sent', 'skipped' => 'Skipped', 'failed' => 'Failed'] as $k => $label)
                    <span class="hmu-chip">
                        <span class="hmu-chip-n">{{ number_format($j['counts'][$k] ?? 0) }}</span> {{ $label }}
                    </span>
                @endforeach
            </div>

            @if ($j['skips'] !== [])
                <p class="hmu-skips">
                    Skipped because:
                    @foreach ($j['skips'] as $reason => $n)
                        {{ str_replace('_', ' ', $reason) }} ({{ $n }}){{ ! $loop->last ? ' · ' : '' }}
                    @endforeach
                </p>
            @endif

            @forelse ($j['upcoming'] as $u)
                <div class="hmu-row">
                    <span class="hmu-row-name">{{ $u['template'] }}</span>
                    <span class="hmu-row-meta">
                        {{ $u['partner'] }} · {{ $u['recipient'] }} ·
                        <span class="{{ $u['due'] ? 'hmu-bad' : '' }}">{{ $u['when'] }}</span>
                    </span>
                </div>
            @empty
                <p class="hmu-empty-line">Nothing queued — no confirmed bookings inside the scheduling horizon.</p>
            @endforelse
        </section>

        {{-- ---------- Recent non-deliveries ---------- --}}
        <section class="hmu-card">
            <h3 class="hmu-card-title">Recent non-deliveries</h3>
            <p class="hmu-card-sub">The last 25 messages that didn't reach anyone, newest first.</p>

            @forelse ($failures as $f)
                <div class="hmu-fail">
                    <span class="hmu-fail-top">
                        <span class="hmu-pill">{{ $f['channel'] }}</span>
                        <span class="hmu-pill tone-bad">{{ $reasonLabel[$f['status']] ?? $f['status'] }}</span>
                        <span class="hmu-fail-to">{{ $f['recipient'] }}</span>
                        <span class="hmu-fail-when">{{ $f['when'] }}</span>
                    </span>
                    <span class="hmu-fail-sub">
                        {{ $f['partner'] }}@if ($f['template']) · {{ $f['template'] }}@endif
                    </span>
                    @if ($f['error'])
                        <code class="hmu-fail-err">{{ $f['error'] }}</code>
                    @endif
                </div>
            @empty
                <p class="hmu-empty-line">Every message reached its recipient this period.</p>
            @endforelse
        </section>
    </div>

    <style>
        .hmu {
            --card: var(--hrn-surface, #ffffff);
            --border: var(--hrn-border, #e2e8f0);
            --ink: var(--hrn-ink, #070c18);
            --ink2: var(--hrn-ink-2, #2d3748);
            --ink3: var(--hrn-ink-3, #5a6a85);
            --bad: var(--hrn-down, #dc2626);
            --ok: var(--hrn-ok, #059669);
            --accent: var(--primary-500, #10b981);
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* ── Period Switcher ────────────────────────────────────────── */
        .hmu-months {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .hmu-month {
            border: 1px solid var(--border);
            cursor: pointer;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--ink2);
            padding: 7px 15px;
            border-radius: 999px;
            background: var(--card);
            box-shadow: var(--hrn-shadow-xs);
            transition: all 0.16s var(--hrn-ease-spring);
        }
        .hmu-month:hover {
            border-color: color-mix(in srgb, var(--accent) 35%, var(--border));
            color: var(--ink);
            transform: translateY(-1px);
        }
        .hmu-month.is-on {
            background: var(--accent);
            color: #ffffff;
            border-color: var(--accent);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35);
        }
        .hmu-period {
            margin-left: auto;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--ink3);
        }

        /* ── Hero Telemetry ─────────────────────────────────────────── */
        .hmu-hero {
            border-radius: var(--hrn-radius-lg);
            padding: 22px 28px;
            color: #ffffff;
            box-shadow: 0 16px 36px -12px rgba(5, 150, 105, 0.45), 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        .hmu-hero-top { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .hmu-h1 { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -0.02em; }
        .hmu-beta {
            font-size: 10px; font-weight: 800; letter-spacing: 0.1em;
            text-transform: uppercase; color: #ffffff;
            background: rgba(255, 255, 255, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.4);
            padding: 3px 9px; border-radius: 999px; line-height: 1;
        }
        .hmu-hsub { margin: 4px 0 0; font-size: 13.5px; color: rgba(255, 255, 255, 0.9); }

        /* ── KPIs (Stripe Telemetry Aesthetic) ──────────────────────── */
        .hmu-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
        }
        .hmu-kpi {
            background: var(--card);
            border-radius: var(--hrn-radius);
            padding: 18px 20px;
            border: 1px solid var(--border);
            box-shadow: var(--hrn-shadow-card);
            display: flex;
            flex-direction: column;
            transition: transform var(--hrn-transition-smooth),
                        box-shadow var(--hrn-transition-smooth),
                        border-color var(--hrn-transition-smooth);
        }
        .hmu-kpi:hover {
            transform: translateY(-2px);
            box-shadow: var(--hrn-shadow-hover);
            border-color: color-mix(in srgb, var(--accent) 35%, var(--border));
        }
        .hmu-kpi-lab {
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--ink3);
        }
        .hmu-kpi-val {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--ink);
            margin: 8px 0 3px;
            font-variant-numeric: tabular-nums;
            line-height: 1.1;
        }
        .hmu-kpi-val.tone-bad { color: var(--bad); }
        .hmu-kpi-val.tone-ok { color: var(--ink); }
        .hmu-kpi-sub { font-size: 12px; color: var(--ink3); line-height: 1.45; }

        /* ── Cards & Data Grids ─────────────────────────────────────── */
        .hmu-split {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 14px;
        }
        .hmu-card {
            background: var(--card);
            border-radius: var(--hrn-radius);
            padding: 20px 22px;
            border: 1px solid var(--border);
            box-shadow: var(--hrn-shadow-card);
        }
        .hmu-card-title {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.015em;
        }
        .hmu-card-sub {
            margin: 4px 0 14px;
            font-size: 12.5px;
            color: var(--ink3);
            line-height: 1.45;
        }
        .hmu-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-top: 1px solid var(--border);
        }
        .hmu-row:first-of-type { border-top: 0; }
        .hmu-row-name { font-size: 13.5px; font-weight: 700; color: var(--ink); }
        .hmu-row-meta { font-size: 12.5px; color: var(--ink2); font-variant-numeric: tabular-nums; }
        .hmu-bad { color: var(--bad); font-weight: 700; }
        .hmu-empty-line { font-size: 12.5px; color: var(--ink3); margin: 8px 0 0; }

        /* ── Journey Queue ──────────────────────────────────────────── */
        .hmu-note {
            font-size: 12.5px;
            line-height: 1.5;
            color: #92400e;
            background: #fffbeb;
            border-radius: 11px;
            padding: 11px 14px;
            margin-bottom: 14px;
            box-shadow: inset 0 0 0 1px rgba(217, 119, 6, 0.22);
        }
        .dark .hmu-note { color: #fcd34d; background: rgba(245, 158, 11, 0.12); }
        .hmu-note code {
            font-size: 11.5px; padding: 2px 6px; border-radius: 5px;
            background: rgba(0, 0, 0, 0.06);
        }
        .dark .hmu-note code { background: rgba(255, 255, 255, 0.1); }
        .hmu-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .hmu-chip {
            font-size: 12px; font-weight: 600; color: var(--ink2);
            padding: 5px 12px; border-radius: 999px;
            border: 1px solid var(--border); background: var(--hrn-surface);
        }
        .hmu-chip-n { font-weight: 800; color: var(--ink); font-variant-numeric: tabular-nums; }
        .hmu-skips { font-size: 12px; color: var(--ink3); margin: 0 0 8px; }

        /* ── High-Precision Data Table ──────────────────────────────── */
        .hmu-table-wrap { overflow-x: auto; }
        .hmu-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 520px; }
        .hmu-table th {
            text-align: left; font-size: 11px; font-weight: 700; letter-spacing: 0.07em;
            text-transform: uppercase; color: var(--ink3); padding: 0 12px 10px 0;
            border-bottom: 1px solid var(--border);
        }
        .hmu-table th.num, .hmu-table td.num { text-align: right; padding-right: 0; font-variant-numeric: tabular-nums; }
        .hmu-table td {
            padding: 11px 12px 11px 0; border-top: 1px solid var(--border); color: var(--ink);
            transition: background-color 0.12s ease;
        }
        .hmu-table tbody tr:hover td {
            background-color: rgba(15, 23, 42, 0.02);
        }
        .dark .hmu-table tbody tr:hover td {
            background-color: rgba(255, 255, 255, 0.02);
        }
        .hmu-tag {
            display: inline-block; margin-left: 8px; font-size: 10.5px; font-weight: 700;
            padding: 2px 8px; border-radius: 999px; color: var(--ink3);
            border: 1px solid var(--border);
        }

        /* ── Recent Failures ────────────────────────────────────────── */
        .hmu-fail { padding: 12px 0; border-top: 1px solid var(--border); }
        .hmu-fail:first-of-type { border-top: 0; }
        .hmu-fail-top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .hmu-pill {
            font-size: 10.5px; font-weight: 800; padding: 3px 9px; border-radius: 999px;
            text-transform: capitalize; color: var(--ink2); border: 1px solid var(--border);
        }
        .hmu-pill.tone-bad { color: var(--bad); border-color: rgba(220, 38, 38, 0.25); background: rgba(220, 38, 38, 0.08); }
        .hmu-fail-to { font-size: 13px; font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }
        .hmu-fail-when { font-size: 11.5px; color: var(--ink3); margin-left: auto; }
        .hmu-fail-sub { display: block; font-size: 11.5px; color: var(--ink3); margin-top: 4px; }
        .hmu-fail-err {
            display: block; margin-top: 6px; font-size: 11px; line-height: 1.5; color: var(--ink2);
            word-break: break-word; background: var(--hrn-surface-subtle); padding: 6px 10px; border-radius: 6px;
        }

        @media (max-width: 640px) {
            .hmu-fail-when { margin-left: 0; }
        }
    </style>
</x-filament-panels::page>
