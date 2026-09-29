{{-- "Needs you". Only the signals that actually need the organiser are shown,
     as rows in one card — each with its figure, why it matters and where to go.
     When nothing does, the whole block is one quiet line: an all-clear doesn't
     deserve three cards of the dashboard. Data from getSignals(). --}}
@php
    $signals = $this->getSignals();
    // "info" (e.g. new bookings this week) is good news, not a to-do.
    $open = array_values(array_filter($signals, fn (array $s): bool => in_array($s['tone'] ?? 'ok', ['warn', 'danger'], true)));
@endphp

<x-filament-widgets::widget>
    @if ($open === [])
        <div class="pna-calm">
            <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="8.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="m6.6 10.2 2.3 2.3 4.6-4.8" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span><b>Nothing needs you right now.</b>
                No event is about to sell out, no money is stuck waiting to settle, and refunds are normal.</span>
        </div>
    @else
        <section class="pna">
            <header class="pna-head">Needs you</header>
            @foreach ($open as $s)
                <a href="{{ $s['url'] }}" wire:navigate class="pna-row tone-{{ $s['tone'] }}">
                    <i class="pna-dot" aria-hidden="true"></i>
                    <div class="pna-txt">
                        <div class="pna-lab">{{ $s['label'] }} <b>{{ $s['value'] }}</b></div>
                        <div class="pna-hint">{{ $s['hint'] }}</div>
                    </div>
                    <span class="pna-cta">{{ $s['cta'] }}</span>
                </a>
            @endforeach
        </section>
    @endif

    <style>
        .pna-calm{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:14px;
            background:#fff;border:1px solid #e6e9f0;font-size:13px;color:#64748b;line-height:1.45;}
        .pna-calm svg{width:20px;height:20px;color:#0f9d63;flex:none;}
        .pna-calm b{color:#0f172a;font-weight:650;}

        .pna{background:#fff;border:1px solid #e6e9f0;border-radius:16px;overflow:hidden;
            box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .pna-head{padding:13px 18px 9px;font-size:11px;font-weight:700;letter-spacing:.09em;
            text-transform:uppercase;color:#64748b;}
        .pna-row{display:flex;align-items:center;gap:14px;padding:13px 18px;text-decoration:none;
            border-top:1px solid #eef1f6;transition:background-color .15s;}
        .pna-row:hover{background:#f8faff;}
        .pna-dot{width:9px;height:9px;border-radius:50%;flex:none;background:#e8a33d;}
        .pna-row.tone-danger .pna-dot{background:#dc2626;}
        .pna-txt{flex:1;min-width:0;}
        .pna-lab{font-size:13.5px;font-weight:600;color:#0f172a;}
        .pna-lab b{font-weight:740;margin-left:6px;font-variant-numeric:tabular-nums;}
        .pna-hint{font-size:12.5px;color:#64748b;margin-top:2px;}
        .pna-cta{font-size:12.5px;font-weight:650;color:#2563eb;white-space:nowrap;}
        .pna-cta::after{content:"";display:inline-block;width:6px;height:6px;margin-left:6px;
            border:solid currentColor;border-width:1.6px 1.6px 0 0;transform:translateY(-1px) rotate(45deg);}
    </style>
</x-filament-widgets::widget>
