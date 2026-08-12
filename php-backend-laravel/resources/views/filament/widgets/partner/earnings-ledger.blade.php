{{-- Partner earnings ledger: premium transaction cards — poster, who paid, the
     amount, payment + settlement chips and payout date. Sort + Livewire "show
     more". Self-contained markup + inline CSS, theme-aware, no Vite rebuild. --}}
@php
    $rows  = $this->getRows();
    $total = $this->getTotalCount();
    $shown = count($rows);
@endphp

<x-filament-widgets::widget>
    <div class="pel">
        <div class="pel-head">
            <div class="pel-title">
                Earnings ledger
                @if ($total > 0)
                    <span class="pel-count">{{ $shown }} of {{ $total }}</span>
                @endif
            </div>
            @if ($total > 1)
                <label class="pel-sort">
                    <span class="pel-sort-lab">Sort</span>
                    <select wire:model.live="ledgerSort" aria-label="Sort earnings">
                        <option value="recent">Newest first</option>
                        <option value="amount">Highest amount</option>
                    </select>
                </label>
            @endif
        </div>

        @if (empty($rows))
            <div class="pel-empty">
                <div class="pel-empty-ic">💰</div>
                <div class="pel-empty-t">No earnings yet</div>
                <div class="pel-empty-d">Paid bookings against your events and venues will appear here.</div>
            </div>
        @else
            <div class="pel-list">
                @foreach ($rows as $i => $r)
                    <div class="pel-card" wire:key="pel-{{ $i }}" style="--d:{{ min($i, 8) * 40 }}ms">
                        <div class="pel-poster tone-{{ strtolower($r['type']) }}">
                            @if ($r['poster'])
                                <img src="{{ $r['poster'] }}" alt="" loading="lazy" />
                            @else
                                <span class="pel-poster-glyph" aria-hidden="true">
                                    {{ $r['type'] === 'Turf' ? '🏟️' : '🎫' }}
                                </span>
                            @endif
                            <span class="pel-poster-type">{{ $r['type'] }}</span>
                        </div>

                        <div class="pel-main">
                            <div class="pel-name">{{ $r['title'] }}</div>
                            <div class="pel-who">
                                <img class="pel-who-av" src="{{ $r['avatar'] }}" alt="" loading="lazy" />
                                <span>{{ $r['who'] }}</span>
                            </div>
                            <div class="pel-chips">
                                <span class="pel-chip tone-{{ $r['tone'] }}">
                                    <span class="pel-dot" aria-hidden="true"></span>{{ $r['status'] }}
                                </span>
                                <span class="pel-chip {{ $r['settled'] ? 'settle-ok' : 'settle-wait' }}">
                                    @if ($r['settled'])
                                        <svg viewBox="0 0 24 24" fill="none" width="11" height="11" aria-hidden="true">
                                            <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.6"
                                                stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    @endif
                                    {{ $r['settleLbl'] }}
                                </span>
                            </div>
                        </div>

                        <div class="pel-money">
                            <div class="pel-amt">{{ $r['amount'] }}</div>
                            <div class="pel-date">{{ $r['date'] }}</div>
                            @if ($r['payoutOn'])
                                <div class="pel-payout">{{ $r['payoutOn'] }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($shown < $total)
                <button type="button" class="pel-more" wire:click="showMore" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="showMore">Show more · {{ $total - $shown }} left</span>
                    <span wire:loading wire:target="showMore">Loading…</span>
                </button>
            @endif
        @endif
    </div>

    <style>
        .pel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:2px 0 12px;}
        .pel-title{display:flex;align-items:center;gap:9px;font-size:15px;font-weight:800;
            letter-spacing:-.01em;color:#0b1220;}
        .pel-count{font-size:11px;font-weight:700;color:#667085;background:#f1f3f7;
            padding:3px 9px;border-radius:999px;}
        .pel-sort{display:inline-flex;align-items:center;gap:7px;}
        .pel-sort-lab{font-size:12px;font-weight:600;color:#98a2b3;}
        .pel-sort select{font-size:12.5px;font-weight:600;color:#344054;background:#fff;
            border:1px solid #e0e3ea;border-radius:10px;padding:6px 28px 6px 11px;cursor:pointer;
            appearance:none;-webkit-appearance:none;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23667085' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat:no-repeat;background-position:right 9px center;transition:border-color .12s;}
        .pel-sort select:hover{border-color:#c9cfda;}

        .pel-list{display:flex;flex-direction:column;gap:10px;}

        .pel-card{display:flex;align-items:stretch;gap:13px;background:#fff;border:1px solid #ebedf2;
            border-radius:16px;padding:12px;box-shadow:0 1px 2px rgba(11,18,32,.05);
            transition:transform .14s cubic-bezier(.22,.61,.36,1),box-shadow .14s,border-color .14s;
            animation:pel-in .38s cubic-bezier(.22,.61,.36,1) both;animation-delay:var(--d);}
        @keyframes pel-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
        .pel-card:hover{transform:translateY(-2px);border-color:#dfe3ea;
            box-shadow:0 12px 26px -14px rgba(11,18,32,.28);}

        .pel-poster{position:relative;width:58px;flex:none;border-radius:12px;overflow:hidden;
            display:flex;align-items:center;justify-content:center;
            background:linear-gradient(160deg,#eef4ff,#dbe8ff);box-shadow:inset 0 0 0 1px rgba(11,18,32,.05);}
        .pel-poster.tone-turf{background:linear-gradient(160deg,#eafaf1,#d6f5e3);}
        .pel-poster img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
        .pel-poster-glyph{font-size:22px;line-height:1;}
        .pel-poster-type{position:absolute;left:0;right:0;bottom:0;text-align:center;
            font-size:9px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#fff;
            padding:2px 0;background:linear-gradient(180deg,rgba(11,18,32,0),rgba(11,18,32,.72));}

        .pel-main{flex:1;min-width:0;display:flex;flex-direction:column;justify-content:center;gap:5px;}
        .pel-name{font-size:14px;font-weight:700;color:#0b1220;letter-spacing:-.01em;line-height:1.25;
            display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
        .pel-who{display:flex;align-items:center;gap:6px;font-size:12px;color:#667085;min-width:0;}
        .pel-who-av{width:16px;height:16px;border-radius:50%;flex:none;object-fit:cover;}
        .pel-who span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .pel-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:1px;}
        .pel-chip{display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;
            padding:3px 8px;border-radius:999px;line-height:1;letter-spacing:.01em;
            color:#067a48;background:linear-gradient(180deg,#eafaf1,#d6f5e3);
            box-shadow:inset 0 0 0 1px rgba(16,140,86,.16);}
        .pel-dot{width:5px;height:5px;border-radius:50%;background:currentColor;flex:none;}
        .pel-chip.tone-warning{color:#a15c00;background:linear-gradient(180deg,#fff5e6,#ffe9c7);
            box-shadow:inset 0 0 0 1px rgba(194,121,10,.18);}
        .pel-chip.tone-danger{color:#b42318;background:linear-gradient(180deg,#fef0f0,#ffe0e0);
            box-shadow:inset 0 0 0 1px rgba(214,69,80,.18);}
        .pel-chip.tone-gray{color:#475467;background:linear-gradient(180deg,#f4f6f9,#e9edf3);
            box-shadow:inset 0 0 0 1px rgba(102,112,133,.16);}
        .pel-chip.settle-ok{color:#067a48;background:linear-gradient(180deg,#eafaf1,#d6f5e3);
            box-shadow:inset 0 0 0 1px rgba(16,140,86,.18);}
        .pel-chip.settle-wait{color:#8a6d1f;background:linear-gradient(180deg,#fbf6e9,#f4ecd4);
            box-shadow:inset 0 0 0 1px rgba(160,124,20,.18);}

        .pel-money{text-align:right;flex:none;display:flex;flex-direction:column;justify-content:center;
            align-items:flex-end;padding-left:4px;}
        .pel-amt{font-size:18px;font-weight:800;color:#0b1220;letter-spacing:-.03em;line-height:1.05;
            font-variant-numeric:tabular-nums;}
        .pel-date{font-size:11px;color:#98a2b3;margin-top:4px;white-space:nowrap;}
        .pel-payout{font-size:10.5px;font-weight:600;color:#0a7d4e;margin-top:2px;white-space:nowrap;}

        .pel-more{width:100%;margin-top:12px;padding:12px;border-radius:14px;cursor:pointer;
            font-size:13px;font-weight:700;color:#344054;background:#fff;border:1px solid #e6e9ef;
            box-shadow:0 1px 2px rgba(11,18,32,.05);transition:background .12s,border-color .12s,transform .1s;}
        .pel-more:hover{background:#f7f9fc;border-color:#d5dae3;}
        .pel-more:active{transform:scale(.99);}
        .pel-more:disabled{opacity:.6;cursor:default;}

        .pel-empty{text-align:center;padding:34px 16px;background:#fff;border:1px solid #ebedf2;
            border-radius:16px;}
        .pel-empty-ic{font-size:30px;line-height:1;margin-bottom:8px;}
        .pel-empty-t{font-size:15px;font-weight:700;color:#0b1220;}
        .pel-empty-d{font-size:12.5px;color:#667085;margin-top:4px;max-width:34ch;
            margin:4px auto 0;line-height:1.45;}

        /* Dark */
        .dark .pel-title{color:#eef1f6;}
        .dark .pel-count{color:#8b94a5;background:#1a2230;}
        .dark .pel-sort-lab{color:#6b7382;}
        .dark .pel-sort select{color:#cbd2dd;background:#141b26;border-color:#28303d;}
        .dark .pel-card{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .pel-card:hover{border-color:#2a3444;box-shadow:0 12px 26px -14px rgba(0,0,0,.6);}
        .dark .pel-poster{background:linear-gradient(160deg,#0e1c33,#0b1626);box-shadow:inset 0 0 0 1px rgba(255,255,255,.06);}
        .dark .pel-poster.tone-turf{background:linear-gradient(160deg,#0f2a1e,#0c2119);}
        .dark .pel-name{color:#eef1f6;}
        .dark .pel-who{color:#8b94a5;}
        .dark .pel-amt{color:#f4f7fb;}
        .dark .pel-date{color:#6b7382;}
        .dark .pel-payout{color:#28c882;}
        .dark .pel-chip{color:#5ce6a4;background:linear-gradient(180deg,#0f2a1e,#0c2119);
            box-shadow:inset 0 0 0 1px rgba(40,200,130,.22);}
        .dark .pel-chip.tone-warning{color:#f0b862;background:linear-gradient(180deg,#2a2010,#211a0c);
            box-shadow:inset 0 0 0 1px rgba(226,161,60,.24);}
        .dark .pel-chip.tone-danger{color:#f59a9a;background:linear-gradient(180deg,#2a1414,#210f0f);
            box-shadow:inset 0 0 0 1px rgba(240,117,126,.24);}
        .dark .pel-chip.tone-gray{color:#aab3c2;background:linear-gradient(180deg,#1a2230,#151c28);
            box-shadow:inset 0 0 0 1px rgba(139,148,165,.2);}
        .dark .pel-chip.settle-ok{color:#5ce6a4;background:linear-gradient(180deg,#0f2a1e,#0c2119);
            box-shadow:inset 0 0 0 1px rgba(40,200,130,.24);}
        .dark .pel-chip.settle-wait{color:#e6c766;background:linear-gradient(180deg,#221c0d,#1b160a);
            box-shadow:inset 0 0 0 1px rgba(200,160,40,.24);}
        .dark .pel-more{color:#cbd2dd;background:#141b26;border-color:#28303d;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .pel-more:hover{background:#1a2230;border-color:#333c4b;}
        .dark .pel-empty{background:#111722;border-color:#1e2633;}
        .dark .pel-empty-t{color:#eef1f6;}
        .dark .pel-empty-d{color:#8b94a5;}

        @media (prefers-reduced-motion:reduce){.pel-card{animation:none;}}
        @media (max-width:420px){
            .pel-poster{width:50px;}
            .pel-amt{font-size:16px;}
        }
    </style>
</x-filament-widgets::widget>
