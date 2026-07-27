<x-filament-panels::page>
    {{-- The php block below must use the block form, not the parenthesised
         inline form: the inline form mis-compiles when its expression contains
         a nested method call. (Do not write directive tokens in a comment —
         Blade compiles them even here, which is its own landmine.) --}}
    @php
        $e = $this->getRecord();
        $s = $this->heroStats();
        $poster = $s['poster'];
        $status = strtolower($s['status'] ?: 'draft');
        $isLive = $status === 'published';

        // BMS-style demand tag, driven by real sell-through.
        $st = (int) $s['sellThrough'];
        if ($e->is_sold_out || ($s['total'] > 0 && $s['sold'] >= $s['total'])) {
            $demand = ['label' => 'Sold out', 'tone' => 'full'];
        } elseif ($st >= 85) {
            $demand = ['label' => 'Almost full', 'tone' => 'hot'];
        } elseif ($st >= 60) {
            $demand = ['label' => 'Filling fast', 'tone' => 'warm'];
        } elseif ($s['sold'] > 0) {
            $demand = ['label' => 'Selling', 'tone' => 'ok'];
        } else {
            $demand = null;
        }
    @endphp

    <style>
        .eh{position:relative;overflow:hidden;border-radius:20px;color:#fff;background:#1f2937;
            box-shadow:0 18px 44px -20px rgba(11,18,32,.55);}
        .eh-bg{position:absolute;inset:0;background-size:cover;background-position:center;
            transform:scale(1.12);filter:blur(3px) saturate(1.05);}
        .eh-scrim{position:absolute;inset:0;
            background:linear-gradient(115deg,rgba(9,14,26,.90) 0%,rgba(9,14,26,.66) 52%,rgba(9,14,26,.30) 100%);}
        .eh-body{position:relative;z-index:2;padding:26px 28px;display:flex;flex-direction:column;gap:18px;}

        /* Poster thumbnail card + headline sit side by side (BMS lockup). */
        .eh-lead{display:flex;gap:16px;align-items:flex-start;}
        .eh-poster{flex:0 0 auto;width:88px;aspect-ratio:3/4;border-radius:12px;overflow:hidden;
            background:#0b1220 center/cover no-repeat;
            box-shadow:0 10px 26px -10px rgba(0,0,0,.7),inset 0 0 0 1px rgba(255,255,255,.14);}
        .eh-lead-txt{flex:1 1 auto;min-width:0;}

        .eh-tags{display:flex;flex-wrap:wrap;gap:7px;align-items:center;}
        .eh-pill{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;
            letter-spacing:.05em;text-transform:uppercase;padding:3px 11px;border-radius:999px;
            background:rgba(255,255,255,.16);box-shadow:inset 0 0 0 1px rgba(255,255,255,.22);}
        .eh-pill.is-live{background:rgba(16,185,129,.30);box-shadow:inset 0 0 0 1px rgba(16,185,129,.5);}
        .eh-dot{width:7px;height:7px;border-radius:50%;background:#34d399;
            box-shadow:0 0 0 0 rgba(52,211,153,.6);animation:eh-pulse 1.8s infinite;}
        @keyframes eh-pulse{0%{box-shadow:0 0 0 0 rgba(52,211,153,.55);}
            70%{box-shadow:0 0 0 7px rgba(52,211,153,0);}100%{box-shadow:0 0 0 0 rgba(52,211,153,0);}}

        /* Demand tag — colour tracks how full the event is. */
        .eh-demand{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:800;
            letter-spacing:.04em;text-transform:uppercase;padding:3px 10px;border-radius:999px;}
        .eh-demand.tone-ok{background:rgba(59,130,246,.28);box-shadow:inset 0 0 0 1px rgba(96,165,250,.55);color:#dbeafe;}
        .eh-demand.tone-warm{background:rgba(245,158,11,.30);box-shadow:inset 0 0 0 1px rgba(251,191,36,.6);color:#fef3c7;}
        .eh-demand.tone-hot{background:rgba(249,115,22,.34);box-shadow:inset 0 0 0 1px rgba(251,146,60,.6);color:#ffedd5;}
        .eh-demand.tone-full{background:rgba(239,68,68,.32);box-shadow:inset 0 0 0 1px rgba(248,113,113,.6);color:#fee2e2;}

        /* Sell-through progress bar. */
        .eh-prog{display:flex;flex-direction:column;gap:6px;}
        .eh-prog-track{height:6px;border-radius:999px;background:rgba(255,255,255,.16);overflow:hidden;}
        .eh-prog-fill{height:100%;border-radius:999px;
            background:linear-gradient(90deg,#3b82f6,#22d3ee);transition:width .4s ease;}
        .eh-prog-cap{display:flex;justify-content:space-between;font-size:11px;
            color:rgba(255,255,255,.72);letter-spacing:.02em;}
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

        /* Haraan brand lockup on the hero */
        .eh-brand{display:flex;flex-wrap:wrap;align-items:center;gap:11px;margin-bottom:2px;}

        /* Primary CTA on the hero top-right: open the desk registration flow. */
        .eh-reg{margin-left:auto;display:inline-flex;align-items:center;gap:7px;text-decoration:none;
            font-size:13px;font-weight:700;letter-spacing:.01em;padding:8px 15px;border-radius:999px;
            color:#0b2b6b;background:#fff;
            box-shadow:0 8px 20px -10px rgba(0,0,0,.6),inset 0 0 0 1px rgba(255,255,255,.7);
            transition:transform .15s,box-shadow .15s,background .15s;}
        .eh-reg:hover{background:#eef4ff;transform:translateY(-1px);
            box-shadow:0 12px 26px -12px rgba(0,0,0,.7),inset 0 0 0 1px rgba(255,255,255,.9);}
        .eh-reg svg{width:16px;height:16px;}

        /* Top action row above the hero: back link on the left, the manual
           "Sold out" override on the right (in the whitespace over the poster). */
        .eh-topbar{display:flex;align-items:center;justify-content:space-between;
            gap:12px;margin-bottom:12px;}

        /* Manual "Sold out" override toggle — quiet light outline when off (it now
           sits on the page background, not the dark hero); solid red when the host
           has closed sales. */
        /* A real on/off switch: label + track + sliding knob. Off = grey, knob left;
           On (sales closed) = red, knob right. */
        .eh-so{display:inline-flex;align-items:center;gap:9px;cursor:pointer;border:0;background:none;
            font:inherit;font-size:12.5px;font-weight:700;letter-spacing:-.005em;color:#4b5563;
            padding:4px 2px;transition:color .15s;}
        .eh-so:hover{color:#111827;}
        .eh-so-track{position:relative;flex:0 0 auto;width:40px;height:23px;border-radius:999px;
            background:#d1d5db;box-shadow:inset 0 0 0 1px rgba(17,24,39,.08);
            transition:background .2s ease;}
        .eh-so-knob{position:absolute;top:2px;left:2px;width:19px;height:19px;border-radius:50%;
            background:#fff;box-shadow:0 1px 3px rgba(17,24,39,.4);transition:transform .2s ease;}
        .eh-so.is-on{color:#b91c1c;}
        .eh-so.is-on .eh-so-track{background:#dc2626;box-shadow:inset 0 0 0 1px #dc2626;}
        .eh-so.is-on .eh-so-knob{transform:translateX(17px);}
        .eh-so[disabled]{opacity:.6;cursor:default;}
        .dark .eh-so{color:#9ca3af;}
        .dark .eh-so:hover{color:#fff;}
        .dark .eh-so-track{background:rgba(255,255,255,.22);box-shadow:inset 0 0 0 1px rgba(255,255,255,.14);}
        .dark .eh-so.is-on{color:#fca5a5;}
        .dark .eh-so.is-on .eh-so-track{background:#dc2626;box-shadow:inset 0 0 0 1px #dc2626;}
        .eh-mark{display:inline-flex;background:#fff;border-radius:8px;padding:6px 11px;
            box-shadow:inset 0 0 0 1px rgba(0,0,0,.06),0 4px 12px -6px rgba(0,0,0,.4);}
        .eh-mark img{height:22px;width:auto;display:block;}
        .eh-kicker{font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;
            color:rgba(255,255,255,.82);}

        /* Hide Filament's default page header on this page: its title + the green
           "Edit event" action both duplicate the poster hero below. The hero is
           the header now. (Scoped: this <style> only loads on the analytics view.) */
        .fi-header{display:none !important;}

        /* Compact back control — small, quiet, BMS-style. Just an arrow + short
           label. No big surface or shadow — it stays out of the way. */
        /* Back control: a crafted circular icon chip + label (shared style with the
           registration page's back button, so every back control looks the same). */
        .eh-back{display:inline-flex;align-items:center;gap:10px;text-decoration:none;
            align-self:center;color:#4b5563;font-size:13px;font-weight:600;letter-spacing:-.005em;
            transition:color .15s;}
        .eh-back-ic{display:grid;place-items:center;width:34px;height:34px;border-radius:50%;
            background:#fff;color:#374151;border:1px solid rgba(17,24,39,.1);
            box-shadow:0 1px 2px rgba(17,24,39,.06);
            transition:transform .15s,border-color .15s,color .15s,box-shadow .15s;}
        .eh-back-ic svg{width:17px;height:17px;}
        .eh-back-tx{display:flex;flex-direction:column;line-height:1.15;}
        .eh-back-tx small{font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#9ca3af;}
        .eh-back:hover{color:#111827;}
        .eh-back:hover .eh-back-ic{border-color:#2563eb;color:#2563eb;transform:translateX(-2px);
            box-shadow:0 4px 12px -6px rgba(37,99,235,.5);}
        .dark .eh-back{color:#9ca3af;}
        .dark .eh-back-ic{background:rgba(255,255,255,.05);color:#e5e7eb;border-color:rgba(255,255,255,.14);}
        .dark .eh-back:hover{color:#fff;}
        .dark .eh-back:hover .eh-back-ic{border-color:#7da8ff;color:#93c5fd;}

        /* ---- Premium section-header system (applies to every analytics section) ---- */
        /* Divider under each header + room for the brand tag on the right. */
        .fi-section-header{position:relative;padding-right:132px !important;
            border-bottom:1px solid rgba(120,120,120,.16);cursor:pointer;}
        /* Haraan-blue accent bar to the left of every title. */
        .fi-section-header-heading{position:relative;padding-left:13px !important;
            font-size:1.02rem !important;font-weight:800 !important;letter-spacing:-.012em !important;}
        .fi-section-header-heading::before{content:"";position:absolute;left:0;top:1px;bottom:1px;
            width:4px;border-radius:999px;background:linear-gradient(180deg,#3b82f6,#1d5fa8);}
        .fi-section-header-description{padding-left:13px !important;}

        /* Consistent Haraan identity tag on every analytics section header (top-right). */
        .fi-section-header::after{content:"";position:absolute;top:1.15rem;right:3.5rem;
            width:64px;height:19px;border-radius:6px;opacity:.9;pointer-events:none;
            background:#fff url('/images/haraan-wordmark.png') center/76% no-repeat;
            box-shadow:inset 0 0 0 1px rgba(0,0,0,.06);}
        .dark .fi-section-header::after{box-shadow:inset 0 0 0 1px rgba(255,255,255,.16);}

        /* ------------------------- Mobile (BMS-grade phone layout) ------------------------- */
        @media (max-width:640px){
            /* Hero: tighter padding, smaller type, 2-up stat tiles, full-width actions. */
            .eh{border-radius:16px;}
            .eh-body{padding:16px 15px;gap:14px;}
            .eh-brand{gap:9px;}
            .eh-mark{padding:5px 9px;}
            .eh-mark img{height:18px;}
            .eh-kicker{font-size:10px;letter-spacing:.12em;}
            .eh-lead{gap:13px;}
            .eh-poster{width:74px;border-radius:11px;}
            .eh-title{font-size:19px;margin-top:8px;}
            .eh-meta{font-size:12px;gap:2px 10px;}
            .eh-stats{gap:8px;}
            .eh-stat{min-width:calc(50% - 4px);flex:1 1 calc(50% - 4px);padding:10px 12px;}
            .eh-stat-v{font-size:18px;}
            .eh-actions{gap:8px;}
            .eh-btn{flex:1 1 auto;justify-content:center;padding:10px 12px;}

            /* Section headers: keep the Haraan brand mark on phones but smaller and
               tucked in, with the blue accent bar + divider intact. */
            .fi-section-header{padding-right:88px !important;}
            .fi-section-header::after{width:44px;height:14px;top:1.05rem;right:2.6rem;}
            .fi-section-header-heading{font-size:.98rem !important;}

            /* Belt-and-braces: nothing may cause a sideways scroll on a phone. */
            .fi-section-content-ctn{overflow-x:hidden;}
        }
    </style>

    <div class="eh-topbar">
        <a class="eh-back" href="{{ \App\Filament\Resources\Events\EventResource::getUrl('index') }}">
            <span class="eh-back-ic"><x-filament::icon icon="heroicon-m-arrow-left" /></span>
            <span class="eh-back-tx"><small>Back to</small>Events</span>
        </a>
        <button type="button"
                class="eh-so {{ $e->is_sold_out ? 'is-on' : '' }}"
                role="switch"
                aria-checked="{{ $e->is_sold_out ? 'true' : 'false' }}"
                wire:click="toggleSoldOut"
                wire:loading.attr="disabled"
                title="{{ $e->is_sold_out ? 'Tickets are closed — switch off to put back on sale' : 'Switch on to close sales and show “Sold out” to buyers' }}">
            <span class="eh-so-tx">Sold out</span>
            <span class="eh-so-track"><span class="eh-so-knob"></span></span>
        </button>
    </div>

    <div class="eh">
        @if ($poster)
            <div class="eh-bg" style="background-image:url('{{ $poster }}');"></div>
        @endif
        <div class="eh-scrim"></div>
        <div class="eh-body">
            <div class="eh-brand">
                <span class="eh-mark"><img src="{{ asset('images/haraan-wordmark.png') }}" alt="Haraan"></span>
                <span class="eh-kicker">Event Analytics</span>
                @if (\App\Filament\Resources\Events\Pages\EventRegister::canAccess())
                    <a class="eh-reg" href="{{ \App\Filament\Resources\Events\Pages\EventRegister::getUrl(['record' => $e]) }}">
                        <x-filament::icon icon="heroicon-m-user-plus" />
                        <span>Register</span>
                    </a>
                @endif
            </div>
            <div class="eh-lead">
                @if ($poster)
                    <div class="eh-poster" style="background-image:url('{{ $poster }}');"></div>
                @endif
                <div class="eh-lead-txt">
                    <div class="eh-tags">
                        <span class="eh-pill {{ $isLive ? 'is-live' : '' }}">
                            @if ($isLive)<span class="eh-dot"></span>@endif
                            {{ $isLive ? 'Live' : ucfirst($status) }}
                        </span>
                        @if ($demand)
                            <span class="eh-demand tone-{{ $demand['tone'] }}">{{ $demand['label'] }}</span>
                        @endif
                    </div>
                    <div class="eh-title">{{ $e->title }}</div>
                    <div class="eh-meta">
                        @if ($e->venue || $e->location)<span>{{ $e->venue ?: $e->location }}</span>@endif
                        @if ($e->date)
                            {{-- Time lives in its own `time` column (a "g:i A" string); `date` is date-only,
                                 so formatting the time out of it always yields 12:00 AM. --}}
                            <span>{{ $e->date->format('D, d M Y') }}{{ trim((string) $e->time) !== '' ? ' · ' . $e->time : '' }}</span>
                        @endif
                    </div>
                </div>
            </div>

            @if ($s['total'] > 0)
                <div class="eh-prog">
                    <div class="eh-prog-track">
                        <div class="eh-prog-fill" style="width:{{ min(100, max(2, (int) $s['sellThrough'])) }}%;"></div>
                    </div>
                    <div class="eh-prog-cap">
                        <span>{{ number_format($s['sold']) }} of {{ number_format($s['total']) }} sold</span>
                        <span>{{ (int) $s['sellThrough'] }}%</span>
                    </div>
                </div>
            @endif

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
