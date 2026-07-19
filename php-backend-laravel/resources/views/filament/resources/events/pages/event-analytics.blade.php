<x-filament-panels::page>
    {{-- Block @php (NOT inline) — an inline @php($x = $this->method()) with a
         nested call mis-compiles in this Blade version. See memory. --}}
    @php
        $e = $this->getRecord();
        $s = $this->heroStats();
        $poster = $s['poster'];
        $status = strtolower($s['status'] ?: 'draft');
    @endphp

    <style>
        .eh{position:relative;overflow:hidden;border-radius:20px;color:#fff;background:#1f2937;
            box-shadow:0 18px 44px -20px rgba(11,18,32,.55);}
        .eh-bg{position:absolute;inset:0;background-size:cover;background-position:center;transform:scale(1.03);}
        .eh-scrim{position:absolute;inset:0;
            background:linear-gradient(115deg,rgba(9,14,26,.90) 0%,rgba(9,14,26,.66) 52%,rgba(9,14,26,.30) 100%);}
        .eh-body{position:relative;z-index:2;padding:26px 28px;display:flex;flex-direction:column;gap:18px;}
        .eh-pill{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;
            letter-spacing:.05em;text-transform:uppercase;padding:3px 11px;border-radius:999px;
            background:rgba(255,255,255,.16);box-shadow:inset 0 0 0 1px rgba(255,255,255,.22);}
        .eh-pill.is-live{background:rgba(16,185,129,.30);box-shadow:inset 0 0 0 1px rgba(16,185,129,.5);}
        .eh-title{font-size:27px;font-weight:800;letter-spacing:-.02em;line-height:1.12;margin:10px 0 0;}
        .eh-meta{font-size:13.5px;color:rgba(255,255,255,.86);margin-top:6px;
            display:flex;flex-wrap:wrap;gap:4px 14px;}
        .eh-stats{display:flex;flex-wrap:wrap;gap:12px;}
        .eh-stat{background:rgba(255,255,255,.10);box-shadow:inset 0 0 0 1px rgba(255,255,255,.14);
            border-radius:14px;padding:12px 16px;min-width:118px;}
        .eh-stat-v{font-size:22px;font-weight:800;letter-spacing:-.01em;line-height:1;}
        .eh-stat-l{font-size:11px;text-transform:uppercase;letter-spacing:.06em;
            color:rgba(255,255,255,.72);margin-top:6px;}
        .eh-actions{display:flex;flex-wrap:wrap;gap:10px;}
        .eh-btn{display:inline-flex;align-items:center;gap:7px;text-decoration:none;font-size:13px;
            font-weight:600;padding:9px 15px;border-radius:10px;color:#fff;
            background:rgba(255,255,255,.14);box-shadow:inset 0 0 0 1px rgba(255,255,255,.22);transition:background .15s;}
        .eh-btn:hover{background:rgba(255,255,255,.26);}
        .eh-btn svg{width:16px;height:16px;}
    </style>

    <div class="eh">
        @if ($poster)
            <div class="eh-bg" style="background-image:url('{{ $poster }}');"></div>
        @endif
        <div class="eh-scrim"></div>
        <div class="eh-body">
            <div>
                <span class="eh-pill {{ $status === 'published' ? 'is-live' : '' }}">
                    {{ $status === 'published' ? 'Live' : ucfirst($status) }}
                </span>
                <div class="eh-title">{{ $e->title }}</div>
                <div class="eh-meta">
                    @if ($e->venue || $e->location)<span>{{ $e->venue ?: $e->location }}</span>@endif
                    @if ($e->date)<span>{{ $e->date->format('D, d M Y · g:i A') }}</span>@endif
                </div>
            </div>

            <div class="eh-stats">
                <div class="eh-stat">
                    <div class="eh-stat-v">₹{{ number_format($s['revenue']) }}</div>
                    <div class="eh-stat-l">Revenue</div>
                </div>
                <div class="eh-stat">
                    <div class="eh-stat-v">{{ number_format($s['sold']) }}{{ $s['total'] > 0 ? ' / ' . number_format($s['total']) : '' }}</div>
                    <div class="eh-stat-l">Tickets sold</div>
                </div>
                <div class="eh-stat">
                    <div class="eh-stat-v">{{ number_format($s['views']) }}</div>
                    <div class="eh-stat-l">Views</div>
                </div>
                <div class="eh-stat">
                    <div class="eh-stat-v">{{ $s['sellThrough'] }}%</div>
                    <div class="eh-stat-l">Sell-through</div>
                </div>
            </div>

            <div class="eh-actions">
                <a class="eh-btn" href="{{ \App\Filament\Resources\Events\EventResource::getUrl('edit', ['record' => $e]) }}">
                    <x-filament::icon icon="heroicon-m-pencil-square" /> Edit event
                </a>
                <a class="eh-btn" href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('index') }}">
                    <x-filament::icon icon="heroicon-m-calendar-days" /> View bookings
                </a>
            </div>
        </div>
    </div>
</x-filament-panels::page>
