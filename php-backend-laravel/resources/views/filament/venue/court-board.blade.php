{{--
    Courts & slots board (Venue edit/view → Courts & slots tab).
    Each court with the slots it is sold in on the chosen weekday and the rate it charges in
    each — the numbers come from VenueCourt::rateFor(), the same call the desk and checkout make.
--}}
@php
    $days = \App\Models\VenueSlot::WEEKDAYS;
    $chipTone = [
        'own' => ['bg' => 'var(--cc-blue-soft)', 'bd' => 'var(--cc-blue)', 'ink' => 'var(--cc-blue-2)'],
        'slot' => ['bg' => 'var(--cc-surface)', 'bd' => 'var(--cc-blue-mid)', 'ink' => 'var(--cc-ink)'],
        'peak' => ['bg' => 'var(--cc-amber-soft)', 'bd' => 'transparent', 'ink' => 'var(--cc-amber)'],
        'base' => ['bg' => 'var(--cc-sunk)', 'bd' => 'var(--cc-line)', 'ink' => 'var(--cc-ink)'],
    ];
    $sourceLabel = ['own' => 'set for this court', 'slot' => 'slot price, all courts', 'peak' => 'peak rate', 'base' => 'court rate'];
@endphp

@include('filament.partials.cc-styles')

<style>
    .cb { padding: 16px 16px 4px; }
    .cb-top { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
    .cb-top h3 { margin: 0; font-size: 14.5px; font-weight: 650; letter-spacing: -0.01em; }
    .cb-top p { margin: 2px 0 0; font-size: 12.5px; color: var(--cc-ink-3); }
    .cb-seg button { position: relative; }
    .cb-seg button .td { position: absolute; top: 3px; right: 4px; width: 5px; height: 5px; border-radius: 50%; background: var(--cc-green); }
    .cb-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; font-size: 12px; color: var(--cc-ink-2); margin: 0 0 12px; }
    .cb-legend span { display: inline-flex; align-items: center; gap: 6px; }
    .cb-legend i { width: 14px; height: 10px; border-radius: 3px; display: inline-block; border: 1px solid; }

    .cb-court { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 0; border: 1px solid var(--cc-line); border-radius: 14px;
        background: var(--cc-surface); box-shadow: var(--cc-lift); overflow: hidden; margin-bottom: 12px; transition: box-shadow .2s; }
    .cb-court:hover { box-shadow: var(--cc-lift-hi); }
    .cb-court.off { opacity: .62; }
    .cb-side { padding: 14px; background: var(--cc-sunk); border-right: 1px solid var(--cc-line); display: flex; flex-direction: column; gap: 10px; }
    .cb-pitch { width: 100%; height: auto; display: block; }
    .cb-name { font-size: 14.5px; font-weight: 650; letter-spacing: -0.01em; display: flex; align-items: center; gap: 8px; }
    .cb-tag { font-size: 11px; font-weight: 600; color: var(--cc-ink-3); }
    .cb-chips { display: flex; flex-wrap: wrap; gap: 4px; }
    .cb-chips span { font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 6px; background: var(--cc-surface); border: 1px solid var(--cc-line); color: var(--cc-ink-2); }
    .cb-rates { font-size: 12.5px; color: var(--cc-ink-2); line-height: 1.55; }
    .cb-rates b { color: var(--cc-ink); font-weight: 650; }
    .cb-btns { display: flex; flex-wrap: wrap; gap: 6px; margin-top: auto; }
    .cb-btns .cb-btn { flex: 1 1 calc(50% - 3px); }
    .cb-btn { flex: 1; border: 1px solid var(--cc-line); border-bottom-width: 2px; background: var(--cc-surface); color: var(--cc-ink); font-size: 12.5px; font-weight: 600;
        padding: 6px 8px; border-radius: 9px; cursor: pointer; transition: transform .08s, border-color .15s; }
    .cb-btn:hover { border-color: var(--cc-blue-mid); }
    .cb-btn:active { transform: translateY(1px); border-bottom-width: 1px; }
    .cb-btn.pri { background: var(--cc-blue); border-color: var(--cc-blue-2); color: #fff; }

    .cb-main { padding: 14px 16px; min-width: 0; display: flex; flex-direction: column; gap: 10px; }
    .cb-sum { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; font-size: 12.5px; color: var(--cc-ink-3); }
    .cb-sum b { color: var(--cc-ink); font-weight: 650; }
    .cb-slots { display: grid; grid-template-columns: repeat(auto-fill, minmax(78px, 1fr)); gap: 6px; }
    .cb-slot { border-radius: 9px; padding: 6px 8px 5px; border: 1px solid; display: flex; flex-direction: column; gap: 1px; position: relative; }
    button.cb-slot { cursor: pointer; text-align: left; font: inherit; transition: transform .08s, box-shadow .15s; }
    button.cb-slot:hover { box-shadow: 0 0 0 2px var(--cc-blue-mid); }
    button.cb-slot:active { transform: translateY(1px); }
    .cb-src { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 10px 12px; border: 1px solid var(--cc-line); border-radius: 12px; background: var(--cc-sunk); font-size: 12.5px; color: var(--cc-ink-2); margin-bottom: 12px; }
    .cb-src b { color: var(--cc-ink); font-weight: 650; }
    .cb-src.warn { background: var(--cc-amber-soft); border-color: transparent; }
    .cb-src .lnk { color: var(--cc-blue-2); font-weight: 600; background: none; border: 0; padding: 0; cursor: pointer; font-size: 12.5px; }
    .cb-slot .t { font-size: 11px; color: var(--cc-ink-3); font-weight: 550; white-space: nowrap; }
    .cb-slot .r { font-size: 13.5px; font-weight: 700; letter-spacing: -0.01em; }
    .cb-slot .dot { position: absolute; top: 6px; right: 6px; width: 6px; height: 6px; border-radius: 50%; background: var(--cc-blue); }
    .cb-slot.shut { background: repeating-linear-gradient(-45deg, var(--cc-sunk) 0 6px, transparent 6px 11px); border: 1px dashed var(--cc-line); }
    .cb-slot.shut .r { color: var(--cc-ink-3); text-decoration: line-through; font-weight: 600; }
    .cb-empty { display: flex; align-items: center; gap: 16px; padding: 18px; border: 1px dashed var(--cc-line); border-radius: 14px; margin-bottom: 12px; color: var(--cc-ink-2); font-size: 13px; }
    .cb-empty b { color: var(--cc-ink); display: block; font-size: 14px; margin-bottom: 2px; }

    /* The board IS the courts list: the plain table under it repeated every court, so it is
       hidden. Its row actions (edit, delete, slot prices) still run from the board buttons. */
    .fi-ta:has(.cb) .fi-ta-content-ctn > :not(.fi-ta-header-ctn),
    .fi-ta:has(.cb) .fi-ta-header-toolbar,
    .fi-ta:has(.cb) .fi-ta-empty-state,
    .fi-ta:has(.cb) .fi-pagination { display: none !important; }
    .cb-btn.del { flex: none; color: var(--cc-red, #b91c1c); }

    @media (max-width: 820px) {
        .cb-court { grid-template-columns: 1fr; }
        .cb-side { border-right: 0; border-bottom: 1px solid var(--cc-line); display: grid; grid-template-columns: 110px 1fr; align-items: start; }
        .cb-side .cb-pitch { grid-row: span 4; }
        .cb-btns { grid-column: 1 / -1; }
    }
</style>

<div class="cc cb">
    <div class="cb-top">
        <div>
            <h3>What each court charges, slot by slot</h3>
            <p>
                {{ $isToday ? 'Today' : 'Next' }} {{ $day }}, {{ $date->format('j M') }}
                · {{ $slotCount }} {{ $slotCount === 1 ? 'slot' : 'slots' }} of {{ $slotMinutes }} min
                · tap a slot to change its price or close it
            </p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap">
        <div class="cc-seg cb-seg" role="group" aria-label="Day">
            @foreach ($days as $d)
                <button type="button" wire:click="setBoardDay('{{ $d }}')" aria-pressed="{{ $d === $day ? 'true' : 'false' }}" title="{{ $d }}">
                    {{ substr($d, 0, 3) }}@if ($d === $todayName)<span class="td" aria-label="today"></span>@endif
                </button>
            @endforeach
        </div>
        <button type="button" class="cb-btn pri" style="flex:none; padding: 8px 14px" wire:click="mountTableAction('create')">+ Add court</button>
        </div>
    </div>

    @php $jump = "const el=document.querySelector('[data-step=vf-hours]'); if(el){el.scrollIntoView({behavior:'smooth',block:'start'})}else{location.href='".$hoursUrl."'}"; @endphp
    @if ($hasHours && $inSync)
        <div class="cb-src">
            <span>Slot times come from the opening hours: <b>{{ $hoursText }}</b> · a slot every <b>{{ $slotMinutes }} min</b>.</span>
            <button type="button" class="lnk" onclick="{{ $jump }}">Change hours &rarr;</button>
        </div>
    @elseif ($hasHours)
        <div class="cb-src warn">
            <span><b>Slots don&rsquo;t match the opening hours</b> ({{ $hoursText }}, every {{ $slotMinutes }} min). Some were made by the old slot generator.</span>
            <button type="button" class="cb-btn pri" style="flex:none; padding: 6px 12px" wire:click="mountAction('syncSlots')">Match slots to hours</button>
        </div>
    @else
        <div class="cb-src warn">
            <span><b>No opening hours set.</b> Slot times come from the hours &mdash; set them (or switch on &ldquo;Open 24 hours&rdquo;) and save.</span>
            <button type="button" class="lnk" onclick="{{ $jump }}">Set opening hours &rarr;</button>
        </div>
    @endif

    @if (empty($courts))
        <div class="cb-empty">
            <svg width="110" height="70" viewBox="0 0 120 80" fill="none" aria-hidden="true">
                <rect x="4" y="6" width="112" height="68" rx="4" stroke="var(--cc-ink-3)" stroke-width="1.5" stroke-dasharray="4 4"/>
                <path d="M60 6v68" stroke="var(--cc-ink-3)" stroke-width="1.2" stroke-dasharray="3 4"/>
                <circle cx="60" cy="40" r="10" stroke="var(--cc-ink-3)" stroke-width="1.2" stroke-dasharray="3 4"/>
                <path d="M60 34v12M54 40h12" stroke="var(--cc-blue)" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <div><b>No courts yet</b>Add the first court with “+ Add court” above. Its slots and prices will appear here.</div>
        </div>
    @elseif (! $hasSlots)
        <div class="cb-empty">
            <svg width="70" height="70" viewBox="0 0 70 70" fill="none" aria-hidden="true">
                <circle cx="35" cy="35" r="28" fill="var(--cc-surface)" stroke="var(--cc-ink)" stroke-width="1.6"/>
                @for ($i = 0; $i < 12; $i++)
                    @php $a = deg2rad($i * 30); @endphp
                    <path d="M{{ 35 + 24 * sin($a) }} {{ 35 - 24 * cos($a) }} L{{ 35 + 21 * sin($a) }} {{ 35 - 21 * cos($a) }}" stroke="var(--cc-ink-3)" stroke-width="1.4"/>
                @endfor
                <path d="M35 35V19M35 35l9 6" stroke="var(--cc-blue)" stroke-width="2.2" stroke-linecap="round"/>
            </svg>
            <div><b>No slots yet, so nothing can be booked</b>Set the opening hours in the form above (or switch on “Open 24 hours”) and save. A slot is made every slot length inside them, for every court below.</div>
        </div>
    @endif

    @if (! empty($courts) && $hasSlots)
        <div class="cb-legend">
            @foreach ($chipTone as $k => $t)
                <span><i style="background: {{ $t['bg'] }}; border-color: {{ $t['bd'] === 'transparent' ? $t['bg'] : $t['bd'] }}"></i>{{ ucfirst($sourceLabel[$k]) }}</span>
            @endforeach
            <span><i style="background: repeating-linear-gradient(-45deg, var(--cc-sunk) 0 3px, transparent 3px 6px); border: 1px dashed var(--cc-line)"></i>Not sold (closed, outside court hours or other sport)</span>
        </div>
    @endif

    @foreach ($courts as $c)
        <div class="cb-court {{ $c['active'] ? '' : 'off' }}">
            <div class="cb-side">
                @include('filament.venue.court-pitch', ['sport' => $c['sport'], 'kind' => $c['kind']])
                <div>
                    <div class="cb-name">{{ $c['name'] }} @unless ($c['active'])<span class="cb-tag">· turned off</span>@endunless</div>
                    <div class="cb-tag">{{ \App\Models\VenueCourt::KINDS[$c['kind']] ?? 'Court' }}</div>
                </div>
                <div class="cb-chips">
                    @forelse ($c['sports'] as $s)
                        <span>{{ $s }}</span>
                    @empty
                        <span>Any sport</span>
                    @endforelse
                </div>
                <div class="cb-rates">
                    Base <b class="cc-num">₹{{ number_format($c['base']) }}</b>/hr{{ $c['baseFromVenue'] ? ' (venue rate)' : '' }}
                    <br>Hours <b>{{ $c['hours'] ?? 'Same as venue' }}</b>
                    @if ($c['peak'])
                        <br>Peak <b class="cc-num" style="color: var(--cc-amber)">₹{{ number_format($c['peak']) }}</b> · {{ $c['peakWhen'] }}
                    @endif
                </div>
                <div class="cb-btns">
                    <button type="button" class="cb-btn pri" wire:click="mountTableAction('slotPrices', '{{ $c['id'] }}')">Slot prices</button>
                    <button type="button" class="cb-btn" wire:click="mountTableAction('courtHours', '{{ $c['id'] }}')">Hours</button>
                    <button type="button" class="cb-btn" wire:click="mountTableAction('edit', '{{ $c['id'] }}')">Edit</button>
                    <button type="button" class="cb-btn del" title="Delete court" aria-label="Delete {{ $c['name'] }}" wire:click="mountTableAction('delete', '{{ $c['id'] }}')">Delete</button>
                </div>
            </div>

            <div class="cb-main">
                @if (! $c['active'])
                    <div class="cc-muted-empty">Turned off, so it is not sold in any slot. Edit → Open for booking to bring it back.</div>
                @elseif (empty($c['chips']))
                    <div class="cc-muted-empty">Closed on {{ $day }}s. Add {{ $day }} to the opening hours to sell it.</div>
                @else
                    <div class="cb-sum">
                        <span><b class="cc-num">{{ $c['openCount'] }}</b> of {{ count($c['chips']) }} slots sold on {{ $day }}</span>
                        @if ($c['openCount'] > 0)
                            <span>
                                @if ($c['min'] === $c['max'])
                                    <b class="cc-num">₹{{ number_format($c['min']) }}</b> every slot
                                @else
                                    <b class="cc-num">₹{{ number_format($c['min']) }} – ₹{{ number_format($c['max']) }}</b> per slot
                                @endif
                            </span>
                        @endif
                    </div>
                    <div class="cb-slots">
                        @foreach ($c['chips'] as $chip)
                            @php $t = $chipTone[$chip['source']]; @endphp
                            @if ($chip['open'])
                                <button type="button" class="cb-slot" style="background: {{ $t['bg'] }}; border-color: {{ $t['bd'] === 'transparent' ? $t['bg'] : $t['bd'] }}"
                                     wire:click="mountAction('editSlot', { slot: {{ $chip['id'] }} })"
                                     title="{{ $chip['time'] }} · ₹{{ number_format($chip['rate']) }} · {{ $sourceLabel[$chip['source']] }}{{ $chip['day'] !== 'Every day' ? ' · '.$chip['day'].' only' : '' }} · tap to change">
                                    @if ($chip['source'] === 'own')<span class="dot"></span>@endif
                                    <span class="t">{{ $chip['time'] }}</span>
                                    <span class="r cc-num" style="color: {{ $t['ink'] }}">₹{{ number_format($chip['rate']) }}</span>
                                </button>
                            @else
                                <button type="button" class="cb-slot shut" wire:click="mountAction('editSlot', { slot: {{ $chip['id'] }} })" title="{{ $chip['time'] }} · {{ $chip['why'] }} · tap to change">
                                    <span class="t">{{ $chip['time'] }}</span>
                                    <span class="r cc-num">{{ ($chip['afterHours'] ?? false) ? 'Closed' : '₹'.number_format($chip['rate']) }}</span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
