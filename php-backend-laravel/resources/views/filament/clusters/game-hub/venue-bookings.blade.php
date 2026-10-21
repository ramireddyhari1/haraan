{{--
    Day bookings — the web twin of the partner app's day screen.
    Date strip · the day in one card · courts × times grid · walk-in and booking
    sheets. Everything on it comes from VenueDayGrid / the page's stats(); the
    styles are scoped to .vdb so the page reads the same in /partner and /control.
--}}
@php
    $grid = $this->grid();
    $s = $this->stats();
    $courts = $grid['courts'] ?? [];
    $slots = $grid['slots'] ?? [];
    $blocked = (bool) ($grid['is_blocked'] ?? false);
    $now = $this->nowMinutes();
    $len = $this->slotLength();
    $isPastDay = $this->date < \App\Support\BusinessClock::today();
    $cols = max(1, count($courts));
    $owing = array_flip($s['owing_ids']);
    $inr = function ($n): string {
        $n = (int) round((float) $n);
        $str = (string) abs($n);
        if (strlen($str) > 3) {
            $str = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($str, 0, -3)) . ',' . substr($str, -3);
        }
        return ($n < 0 ? '-' : '') . '₹' . $str;
    };
    $dayLabel = \Illuminate\Support\Carbon::parse($this->date)->format('l, j F');
    $ring = 2 * M_PI * 40;
    $booking = $this->openBooking();
    $seatSlot = $this->seatSlotId ? collect($slots)->firstWhere('slot_id', (int) $this->seatSlotId) : null;
    $seatCourt = $this->seatCourtId ? collect($courts)->firstWhere('id', (int) $this->seatCourtId) : null;
    $seatCell = $seatSlot && $seatCourt ? collect($seatSlot['courts'])->firstWhere('court_id', (int) $this->seatCourtId) : null;
    $seatPrice = (float) ($seatCell['price'] ?? $seatSlot['price'] ?? 0);
    $durations = $seatSlot ? $this->durationOptions() : [];
    $picked = collect($durations)->firstWhere('units', (int) $this->hours);
    $perLabel = $len < 60 ? 'per ' . $len . ' min' : 'an hour';
    $seatFrom = $seatSlot ? $this::clock($seatSlot['time']) : '';
    $block = $this->openBlock();
@endphp

<x-filament-panels::page>
<div class="vdb" x-data x-on:vdb-close.window="$wire.closeSheets()"
     x-on:keydown.window="
        if ($event.target.closest?.('input,select,textarea')) return;
        if ($event.key === 'ArrowLeft') { $wire.shiftDay(-1) }
        if ($event.key === 'ArrowRight') { $wire.shiftDay(1) }
        if ($event.key === 'Escape') { $wire.closeSheets() }
     ">

    {{-- ── Venue + Day Context Header ───────────────────────────────────── --}}
    <div class="vdb-top">
        <div class="vdb-where">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-5.6-7-11a7 7 0 0 1 14 0c0 5.4-7 11-7 11Z"/><circle cx="12" cy="10" r="2.6"/></svg>
            @if ($this->venueOptions()->count() > 1)
                <select wire:model.live="venueId" aria-label="Venue">
                    @foreach ($this->venueOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            @else
                <span class="vdb-venue-name">{{ $this->venueOptions()->first() ?? 'No venue yet' }}</span>
            @endif
        </div>
        <div class="vdb-top-end">
            <span class="vdb-dayname">{{ $dayLabel }}</span>
            @if ($this->venue())
                <button type="button" @class(['vdb-day-status', 'is-closed' => $blocked]) wire:click="toggleClosed"
                        wire:confirm="{{ $blocked ? 'Reopen this day for bookings?' : 'Close this day? Nothing can be booked while it is closed.' }}">
                    <span class="vdb-status-dot"></span>
                    <span>{{ $blocked ? 'Reopen day' : 'Close day' }}</span>
                </button>
            @endif
        </div>
    </div>

    {{-- ── Date Navigator Strip ────────────────────────────────────────── --}}
    <div class="vdb-strip-wrap">
        <button type="button" class="vdb-arrow" wire:click="shiftDay(-1)" aria-label="Previous day">
            <svg viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg>
        </button>
        <div class="vdb-strip" x-init="$nextTick(() => $el.querySelector('.is-on')?.scrollIntoView({ inline: 'center', block: 'nearest' }))">
            @foreach ($this->days() as $d)
                <button type="button" wire:key="day-{{ $d['date'] }}" wire:click="selectDate('{{ $d['date'] }}')" data-press
                        @class(['vdb-day', 'is-on' => $d['date'] === $this->date, 'is-today' => $d['is_today']])>
                    <span class="vdb-day-dow">{{ $d['dow'] }}</span>
                    <span class="vdb-day-num">{{ $d['day'] }}</span>
                    <span class="vdb-day-count">{{ $d['count'] > 0 ? $d['count'] . ' booked' : $d['month'] }}</span>
                    @if ($d['is_today'])<i class="vdb-today-indicator" title="Today"></i>@endif
                </button>
            @endforeach
        </div>
        <button type="button" class="vdb-arrow" wire:click="shiftDay(1)" aria-label="Next day">
            <svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg>
        </button>
    </div>

    @if (! $this->venue())
        <section class="vdb-empty">
            <img src="{{ asset('images/partner/empty-desk.svg') }}" alt="" width="170" height="118">
            <h3>No venue to show yet</h3>
            <p>Once a venue is set up on your account, its courts and bookings appear here day by day.</p>
        </section>
    @else

    {{-- ── Executive Day KPI Card ───────────────────────────────────────── --}}
    <section class="vdb-card" wire:key="card-{{ $this->venueId }}-{{ $this->date }}">
        @if ($blocked)
            <div class="vdb-closed-row">
                <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.5"/><path d="m4.8 15.2 10.4-10.4"/></svg>
                <span>Closed on this day — nothing can be booked.</span>
                <button type="button" wire:click="toggleClosed">Reopen</button>
            </div>
        @endif

        <div class="vdb-card-kpi">
            <div class="vdb-kpi-main">
                <div class="vdb-kpi-row">
                    <div class="vdb-money-group">
                        <div class="vdb-lab">Expected</div>
                        <div class="vdb-big" data-count-to="{{ (int) round($s['expected']) }}" data-count-prefix="₹" data-count-key="exp">{{ $inr($s['expected']) }}</div>
                    </div>
                    <div class="vdb-occ-badge" title="{{ $s['occupancy'] }}% booked">
                        <span class="vdb-occ-pct"><span data-count-to="{{ $s['occupancy'] }}" data-count-key="occ">{{ $s['occupancy'] }}</span>%</span>
                        <span class="vdb-occ-tag">Full</span>
                    </div>
                </div>

                <div class="vdb-bar-section">
                    <div class="vdb-bar" aria-label="Collected share"><i style="width: {{ $s['collected_share'] }}%"></i></div>
                    <div class="vdb-collected-info">
                        <span class="vdb-collected-sum"><b @class(['is-in' => $s['collected'] > 0])>{{ $inr($s['collected']) }}</b> collected</span>
                        @if ($s['split'])<span class="vdb-split-details">· {{ $s['split'] }}</span>@endif
                    </div>
                </div>
            </div>
        </div>

        @if ($s['due'] > 0)
            <button type="button" wire:click="toggleDue" data-press @class(['vdb-due', 'is-on' => $this->filter === 'due'])>
                <i class="vdb-due-beacon"></i>
                <span class="vdb-due-label">
                    <b>{{ $inr($s['due']) }} still to collect</b>
                    <em>{{ $s['due_count'] }} {{ \Illuminate\Support\Str::plural('booking', $s['due_count']) }} unpaid</em>
                </span>
                <strong class="vdb-due-action">{{ $this->filter === 'due' ? 'Show all' : 'Show them' }}</strong>
            </button>
        @endif

        <div class="vdb-counts">
            <div class="vdb-count-box"><b>{{ $s['total'] }}</b><span>Court-hours</span></div>
            <div class="vdb-count-box"><b @class(['is-blue' => $s['booked'] > 0])>{{ $s['booked'] }}</b><span>Booked</span></div>
            <div class="vdb-count-box"><b @class(['is-green' => $s['open'] > 0])>{{ $s['open'] }}</b><span>@if ($s['open'] > 0)<i class="vdb-open-pip"></i>@endif Open</span></div>
        </div>
    </section>

    {{-- ── Courts × times ──────────────────────────────────────────────── --}}
    @if ($blocked)
        <section class="vdb-empty">
            <svg class="vdb-illo" viewBox="0 0 220 150" aria-hidden="true">
                <ellipse cx="110" cy="138" rx="92" ry="7" fill="#EDF1F7"/>
                <g stroke="#8C9AB3" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M38 132V34M182 132V34"/>
                    <path d="M38 40h144"/>
                    @foreach (range(0, 13) as $i)
                        <path d="M{{ 48 + $i * 10 }} 40v92" stroke="#C3CDDC" stroke-width="1.4"/>
                    @endforeach
                    <path d="M38 132h144"/>
                </g>
                <path d="M40 60c30 20 50 24 70 22s40-4 70-22" stroke="#64748B" stroke-width="3.2" fill="none" stroke-dasharray="7 4" stroke-linecap="round"/>
                <g transform="translate(110 82)">
                    <path d="M-11-6v-7a11 11 0 0 1 22 0v7" fill="none" stroke="#475569" stroke-width="3.4"/>
                    <rect x="-16" y="-7" width="32" height="26" rx="5" fill="#2563EB"/>
                    <circle cy="4" r="3.4" fill="#fff"/>
                    <path d="M0 6v6" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/>
                </g>
            </svg>
            <h3>Closed on {{ \Illuminate\Support\Carbon::parse($this->date)->format('l') }}</h3>
            <p>The courts can't be booked on this day. Reopen it if plans change.</p>
            <x-filament::button wire:click="toggleClosed">Reopen day</x-filament::button>
        </section>
    @elseif ($slots === [])
        <section class="vdb-empty">
            <img src="{{ asset('images/partner/empty-desk.svg') }}" alt="" width="170" height="118">
            <h3>No time slots on this day</h3>
            <p>This venue has no opening hours set for {{ \Illuminate\Support\Carbon::parse($this->date)->format('l') }}s. Add them in the venue's slots and they'll show up here.</p>
        </section>
    @else
        @php
            $pastRows = $now === null ? 0 : collect($slots)->filter(function ($sl) use ($now, $len) {
                $st = \App\Services\BookingService::timeToMinutes($sl['time']);
                return $st !== null && $st + $len <= $now;
            })->count();
        @endphp
        <div x-data="{ early: false }" class="vdb-grid-block">
        @if ($pastRows > 0)
            <button type="button" class="vdb-earlier" x-on:click="early = !early" data-press>
                <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.2l2 1.3"/></svg>
                <span x-text="early ? 'Hide the {{ $pastRows }} slots already played' : '{{ $pastRows }} {{ \Illuminate\Support\Str::plural('slot', $pastRows) }} already played today'"></span>
                <b x-text="early ? 'Hide' : 'Show'"></b>
            </button>
        @endif
        <section @class(['vdb-grid-card', 'is-due' => $this->filter === 'due', 'is-wide' => $cols > 3]) x-bind:class="{ 'hide-past': ! early }">
            <div class="vdb-grid" style="--cols: {{ $cols }}">
                <div class="vdb-corner">Time</div>
                @forelse ($courts as $court)
                    <div class="vdb-court" wire:key="court-{{ $court['id'] }}">
                        <b>{{ $court['name'] }}</b>
                        @if (! empty($court['sports']))<span>{{ implode(', ', array_map('ucfirst', $court['sports'])) }}</span>@endif
                    </div>
                @empty
                    <div class="vdb-court"><b>{{ $grid['venue']['name'] }}</b></div>
                @endforelse

                @foreach ($slots as $slot)
                    @php
                        $start = \App\Services\BookingService::timeToMinutes($slot['time']);
                        $isNow = $now !== null && $start !== null && $now >= $start && $now < $start + $len;
                        $isPast = $isPastDay || ($now !== null && $start !== null && $start + $len <= $now);
                        $cells = count($courts) > 0 ? $slot['courts'] : [[
                            'court_id' => 0, 'is_booked' => $slot['booked'] > 0, 'is_held' => $slot['held'] > 0 && $slot['booked'] === 0,
                            'allowed' => true, 'price' => $slot['price'], 'is_peak' => false, 'bookings' => $slot['bookings'],
                        ]];
                    @endphp
                    <div wire:key="t-{{ $slot['slot_id'] }}" @if ($isPast && ! $isPastDay) data-past @endif @class(['vdb-time', 'is-now' => $isNow, 'is-past' => $isPast])>
                        {{ $this::clock($slot['time']) }}
                        @if ($isNow)<em>Now</em>@endif
                    </div>
                    @foreach ($cells as $cell)
                        @php $cellKey = 'c-' . $slot['slot_id'] . '-' . $cell['court_id']; @endphp
                        @if ($cell['is_booked'] && count($cell['bookings']) > 0)
                            @php
                                $b = $cell['bookings'][0];
                                $walkIn = strtolower((string) $b['channel']) === 'offline';
                                $bal = max(0, (float) $b['amount'] - (float) $b['amount_paid']);
                            @endphp
                            <button type="button" wire:key="{{ $cellKey }}" wire:click="showBooking({{ $b['id'] }})" data-press @if ($isPast && ! $isPastDay) data-past @endif
                                    @class(['vdb-cell', 'is-booked', 'is-walkin' => $walkIn, 'is-past' => $isPast,
                                        'is-owing' => isset($owing[$b['id']]), 'is-new' => (int) $this->justBookedId === (int) $b['id']])>
                                <span class="vdb-kicker">
                                    {{ $walkIn ? 'Walk-in' : 'Online' }}
                                    @if ($b['checked_in'] > 0)
                                        <i class="vdb-in"><svg viewBox="0 0 12 12"><path d="m2.5 6.3 2.2 2.2 4.8-5"/></svg>In</i>
                                    @endif
                                </span>
                                <span class="vdb-name">{{ $b['customer'] }}</span>
                                <span class="vdb-amt">
                                    {{ $inr($b['amount']) }}
                                    @if ($bal > 0.5)<em class="is-due">{{ $inr($bal) }} due</em>@elseif ($b['amount'] > 0)<em class="is-paid">Paid</em>@endif
                                </span>
                            </button>
                        @elseif ($cell['is_held'])
                            <div wire:key="{{ $cellKey }}" @if ($isPast && ! $isPastDay) data-past @endif class="vdb-cell is-held" title="A player is paying for this in the app right now">
                                <svg viewBox="0 0 16 16" aria-hidden="true"><rect x="3.5" y="7" width="9" height="6.5" rx="1.6"/><path d="M5.5 7V5.2a2.5 2.5 0 0 1 5 0V7"/></svg>
                                <b>In checkout</b>
                                <span>held while they pay</span>
                            </div>
                        @elseif (! empty($cell['block']))
                            @php $bk = $cell['block']; @endphp
                            <button type="button" wire:key="{{ $cellKey }}" wire:click="showBlock({{ $bk['id'] }})" data-press @if ($isPast && ! $isPastDay) data-past @endif
                                    @class(['vdb-cell', 'is-blocked', 'is-past' => $isPast]) aria-label="Blocked: {{ $bk['reason'] }}">
                                <svg viewBox="0 0 16 16" aria-hidden="true"><rect x="3.5" y="7" width="9" height="6.5" rx="1.6"/><path d="M5.5 7V5.2a2.5 2.5 0 0 1 5 0V7"/></svg>
                                <b>{{ $bk['reason'] }}</b>
                                <span>{{ $bk['note'] ?: ($bk['all_day'] ? 'all day' : 'till ' . $this::clock($bk['end'])) }}</span>
                            </button>
                        @elseif (! $cell['allowed'])
                            <div wire:key="{{ $cellKey }}" @if ($isPast && ! $isPastDay) data-past @endif class="vdb-cell is-off" title="This court isn't sold at this time">
                                <span>Not sold</span>
                            </div>
                        @elseif ($isPast)
                            <div wire:key="{{ $cellKey }}" @if (! $isPastDay) data-past @endif class="vdb-cell is-gone"><span>—</span></div>
                        @else
                            <button type="button" wire:key="{{ $cellKey }}" wire:click="openSeat({{ $slot['slot_id'] }}, {{ $cell['court_id'] }})" data-press
                                    class="vdb-cell is-open" aria-label="Book {{ $this::clock($slot['time']) }}">
                                <span @class(['vdb-open', 'is-peak' => $cell['is_peak']])><i></i>{{ $cell['is_peak'] ? 'Open · Peak' : 'Open' }}</span>
                                <span class="vdb-price">{{ $inr($cell['price']) }}</span>
                                <span class="vdb-plus" aria-hidden="true"><svg viewBox="0 0 16 16"><path d="M8 3.5v9M3.5 8h9"/></svg></span>
                            </button>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </section>
        </div>
        <p class="vdb-hint">Tap an open court to seat a walk-in · tap a booking to check in, collect or cancel · ← → change the day</p>
    @endif
    @endif

    {{-- ── Walk-in sheet ───────────────────────────────────────────────── --}}
    @if ($seatSlot)
        {{-- Teleported to <body>: the page content sits under an animated parent,
             and a transformed ancestor would trap a position:fixed sheet inside it. --}}
        <div wire:key="seat-{{ $this->seatSlotId }}-{{ $this->seatCourtId }}"><template x-teleport="body">
        <div class="vdb-sheet-wrap"
             x-data="{ show: false, close() { this.show = false; setTimeout(() => window.dispatchEvent(new CustomEvent('vdb-close')), 180) } }"
             x-init="$nextTick(() => { show = true; setTimeout(() => $refs.name?.focus(), 220) })"
             x-on:keydown.escape.window="close()">
            <div class="vdb-scrim" x-show="show" x-transition.opacity.duration.180ms x-on:click="close()"></div>
            <aside class="vdb-sheet" x-show="show"
                   x-transition:enter="vdb-sheet-in" x-transition:enter-start="vdb-sheet-from" x-transition:enter-end="vdb-sheet-to"
                   x-transition:leave="vdb-sheet-out" x-transition:leave-start="vdb-sheet-to" x-transition:leave-end="vdb-sheet-from"
                   role="dialog" aria-label="Seat a walk-in">
                <div class="vdb-sheet-handle" aria-hidden="true"></div>
                <header class="vdb-sheet-head">
                    <div>
                        <div class="vdb-lab">{{ $this->sheetMode === 'block' ? 'Block court' : 'Walk-in' }}</div>
                        <h3>{{ $seatCourt['name'] ?? $grid['venue']['name'] }} · {{ $seatFrom }}</h3>
                        <p>{{ $dayLabel }} · {{ $inr($seatPrice) }} {{ $perLabel }} @if ($seatCell['is_peak'] ?? false)(peak)@endif</p>
                    </div>
                    <button type="button" class="vdb-x" x-on:click="close()" aria-label="Close"><svg viewBox="0 0 16 16"><path d="m4 4 8 8M12 4l-8 8"/></svg></button>
                </header>

                <form wire:submit="{{ $this->sheetMode === 'block' ? 'blockSlot' : 'seat' }}" class="vdb-form">
                    <div class="vdb-modes" role="tablist">
                        <button type="button" role="tab" wire:click="$set('sheetMode', 'walkin')" data-press @class(['is-on' => $this->sheetMode !== 'block'])>
                            <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="4.6" r="2.4"/><path d="M3.5 14c.4-2.9 2.2-4.6 4.5-4.6s4.1 1.7 4.5 4.6"/></svg>Walk-in
                        </button>
                        <button type="button" role="tab" wire:click="$set('sheetMode', 'block')" data-press @class(['is-on' => $this->sheetMode === 'block'])>
                            <svg viewBox="0 0 16 16" aria-hidden="true"><rect x="3.5" y="7" width="9" height="6.5" rx="1.6"/><path d="M5.5 7V5.2a2.5 2.5 0 0 1 5 0V7"/></svg>Block slot
                        </button>
                    </div>

                    @if ($this->sheetMode === 'block')
                    <div class="vdb-field">
                        <span>Why</span>
                        <div class="vdb-seg vdb-reasons">
                            @foreach ($this::BLOCK_REASONS as $k => $label)
                                <button type="button" wire:click="$set('blockKind', '{{ $k }}')" data-press @class(['is-on' => $this->blockKind === $k])>{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    @else
                    <label class="vdb-field">
                        <span>Name</span>
                        <input x-ref="name" type="text" wire:model="guestName" placeholder="Who's playing?" autocomplete="off" maxlength="120">
                        @error('guestName')<em>{{ $message }}</em>@enderror
                    </label>

                    <label class="vdb-field">
                        <span>Phone <small>optional — for the WhatsApp receipt</small></span>
                        <div class="vdb-phone" x-data="{ v: @entangle('guestPhone') }">
                            <b>+91</b>
                            <input type="tel" inputmode="numeric" placeholder="98765 43210" maxlength="11"
                                   x-bind:value="v"
                                   x-on:input="let d = $event.target.value.replace(/\D/g, '').slice(0, 10); v = d.length > 5 ? d.slice(0, 5) + ' ' + d.slice(5) : d; $event.target.value = v">
                        </div>
                        @error('guestPhone')<em>{{ $message }}</em>@enderror
                    </label>

                    @endif

                    <div class="vdb-field">
                        <span>How long <small>from {{ $seatFrom }}</small></span>
                        <div class="vdb-dur">
                            @foreach ($durations as $o)
                                <button type="button" wire:click="$set('hours', '{{ $o['units'] }}')" data-press @disabled(! $o['free'])
                                        @class(['is-on' => (int) $this->hours === $o['units'], 'is-off' => ! $o['free']])>
                                    <b>{{ $o['label'] }}</b>
                                    <small>{{ $o['free'] ? 'till ' . $o['until'] : 'taken' }}</small>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @if ($this->sheetMode === 'block')
                    <label class="vdb-field">
                        <span>Note <small>optional — only your team sees it</small></span>
                        <input type="text" wire:model="blockNote" placeholder="e.g. Net repair, Coach Ravi's batch" autocomplete="off" maxlength="120">
                        @error('blockNote')<em>{{ $message }}</em>@enderror
                    </label>

                    <div class="vdb-sheet-foot">
                        <div class="vdb-total">
                            <span>Not bookable by anyone</span>
                            <b>{{ $seatFrom }}{{ $picked ? ' – ' . $picked['until'] : '' }}</b>
                        </div>
                        <button type="submit" class="vdb-primary is-ink" data-haptic wire:loading.attr="disabled" wire:target="blockSlot">
                            <span wire:loading.remove wire:target="blockSlot">Block court</span>
                            <span wire:loading wire:target="blockSlot">Blocking…</span>
                        </button>
                    </div>
                    @else
                    <div class="vdb-field">
                        <span>Payment</span>
                        <div class="vdb-seg">
                            @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'later' => 'Collect later'] as $k => $label)
                                <button type="button" wire:click="$set('payNow', '{{ $k }}')" data-press @class(['is-on' => $this->payNow === $k])>{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="vdb-sheet-foot">
                        <div class="vdb-total">
                            <span>{{ $seatFrom }}{{ $picked ? ' – ' . $picked['until'] : '' }} · {{ (int) $this->hours }} × {{ $inr($seatPrice) }}</span>
                            <b>{{ $inr($seatPrice * (int) $this->hours) }}</b>
                        </div>
                        <button type="submit" class="vdb-primary" data-haptic wire:loading.attr="disabled" wire:target="seat">
                            <span wire:loading.remove wire:target="seat">{{ $this->payNow === 'later' ? 'Book' : 'Book & mark paid' }}</span>
                            <span wire:loading wire:target="seat">Booking…</span>
                        </button>
                    </div>
                    <p class="vdb-fine">Price is what the court charges at this time, peak included. Longer bookings run into the next slots if they're free.</p>
                    @endif
                </form>
            </aside>
        </div>
        </template></div>
    @endif

    {{-- ── Block sheet ─────────────────────────────────────────────────── --}}
    @if ($block)
        <div wire:key="blk-{{ $block['id'] }}"><template x-teleport="body">
        <div class="vdb-sheet-wrap"
             x-data="{ show: false, close() { this.show = false; setTimeout(() => window.dispatchEvent(new CustomEvent('vdb-close')), 180) } }"
             x-init="$nextTick(() => show = true)"
             x-on:keydown.escape.window="close()">
            <div class="vdb-scrim" x-show="show" x-transition.opacity.duration.180ms x-on:click="close()"></div>
            <aside class="vdb-sheet" x-show="show"
                   x-transition:enter="vdb-sheet-in" x-transition:enter-start="vdb-sheet-from" x-transition:enter-end="vdb-sheet-to"
                   x-transition:leave="vdb-sheet-out" x-transition:leave-start="vdb-sheet-to" x-transition:leave-end="vdb-sheet-from"
                   role="dialog" aria-label="Blocked court">
                <div class="vdb-sheet-handle" aria-hidden="true"></div>
                <header class="vdb-sheet-head">
                    <div class="vdb-who">
                        <span class="vdb-av vdb-lockav"><svg viewBox="0 0 16 16" aria-hidden="true"><rect x="3.5" y="7" width="9" height="6.5" rx="1.6"/><path d="M5.5 7V5.2a2.5 2.5 0 0 1 5 0V7"/></svg></span>
                        <div>
                            <h3>{{ $block['reason'] }}</h3>
                            <p>Not bookable by anyone</p>
                        </div>
                    </div>
                    <button type="button" class="vdb-x" x-on:click="close()" aria-label="Close"><svg viewBox="0 0 16 16"><path d="m4 4 8 8M12 4l-8 8"/></svg></button>
                </header>
                <dl class="vdb-facts">
                    <div><dt>When</dt><dd>{{ $block['time'] }}</dd></div>
                    <div><dt>Court</dt><dd>{{ $block['court'] }}</dd></div>
                    @if ($block['note'])<div><dt>Note</dt><dd>{{ $block['note'] }}</dd></div>@endif
                    @if ($block['by'])<div><dt>Blocked by</dt><dd>{{ $block['by'] }}</dd></div>@endif
                </dl>
                <div class="vdb-actions">
                    @if ($block['removable'])
                        <button type="button" class="vdb-primary" data-haptic wire:click="unblock({{ $block['id'] }})"
                                wire:confirm="Open this court again? Players can book it straight away.">Unblock court</button>
                    @else
                        <p class="vdb-fine">This block repeats or covers the whole venue, so it's changed by Haraan, not from the desk.</p>
                    @endif
                </div>
            </aside>
        </div>
        </template></div>
    @endif

    {{-- ── Booking sheet ───────────────────────────────────────────────── --}}
    @if ($booking)
        @php
            $hue = crc32((string) $booking['customer']) % 360;
            $walk = strtolower((string) $booking['channel']) === 'offline';
        @endphp
        <div wire:key="bk-{{ $booking['id'] }}"><template x-teleport="body">
        <div class="vdb-sheet-wrap"
             x-data="{ show: false, close() { this.show = false; setTimeout(() => window.dispatchEvent(new CustomEvent('vdb-close')), 180) } }"
             x-init="$nextTick(() => show = true)"
             x-on:keydown.escape.window="close()">
            <div class="vdb-scrim" x-show="show" x-transition.opacity.duration.180ms x-on:click="close()"></div>
            <aside class="vdb-sheet" x-show="show"
                   x-transition:enter="vdb-sheet-in" x-transition:enter-start="vdb-sheet-from" x-transition:enter-end="vdb-sheet-to"
                   x-transition:leave="vdb-sheet-out" x-transition:leave-start="vdb-sheet-to" x-transition:leave-end="vdb-sheet-from"
                   role="dialog" aria-label="Booking">
                <div class="vdb-sheet-handle" aria-hidden="true"></div>
                <header class="vdb-sheet-head">
                    <div class="vdb-who">
                        <span class="vdb-av" style="background: hsl({{ $hue }} 48% 46%)">{{ mb_strtoupper(mb_substr((string) $booking['customer'], 0, 1)) }}</span>
                        <div>
                            <h3>{{ $booking['customer'] }}</h3>
                            @if ($booking['phone'])
                                @php $digits = preg_replace('/\D/', '', (string) $booking['phone']); $digits = strlen($digits) > 10 ? substr($digits, -10) : $digits; @endphp
                                <a href="tel:+91{{ $digits }}">{{ strlen($digits) === 10 ? substr($digits, 0, 5) . ' ' . substr($digits, 5) : $booking['phone'] }}</a>
                            @else
                                <p>{{ $walk ? 'Walk-in' : 'Booked online' }}</p>
                            @endif
                        </div>
                    </div>
                    <button type="button" class="vdb-x" x-on:click="close()" aria-label="Close"><svg viewBox="0 0 16 16"><path d="m4 4 8 8M12 4l-8 8"/></svg></button>
                </header>

                <dl class="vdb-facts">
                    <div><dt>When</dt><dd>{{ $booking['time'] ?: '—' }}</dd></div>
                    <div><dt>Court</dt><dd>{{ $booking['court'] ?? 'Whole venue' }}</dd></div>
                    <div><dt>How</dt><dd>{{ $walk ? 'Walk-in at the desk' : 'Booked online' }}</dd></div>
                    <div><dt>Amount</dt><dd>{{ $inr($booking['amount']) }}</dd></div>
                    <div><dt>Paid</dt><dd @class(['is-in' => $booking['amount_paid'] > 0])>{{ $inr($booking['amount_paid']) }}</dd></div>
                    @if ($booking['balance'] > 0.5)
                        <div><dt>Still due</dt><dd class="is-due">{{ $inr($booking['balance']) }}</dd></div>
                    @endif
                    <div><dt>Arrived</dt><dd @class(['is-in' => $booking['checked_in'] > 0])>{{ $booking['checked_in'] > 0 ? 'Checked in' : 'Not yet' }}</dd></div>
                    @if ($booking['ticket_code'])
                        <div><dt>Ticket</dt><dd class="vdb-code">{{ $booking['ticket_code'] }}</dd></div>
                    @endif
                </dl>

                <div class="vdb-actions">
                    @if ($booking['checked_in'] === 0 && $this->canCheckIn() && ! $booking['is_hold'])
                        <button type="button" class="vdb-primary" data-haptic wire:click="checkIn({{ $booking['id'] }})">Check in</button>
                    @endif
                    @if ($booking['balance'] > 0.5)
                        <div class="vdb-collect">
                            <span>Collect {{ $inr($booking['balance']) }}</span>
                            <div class="vdb-seg">
                                <button type="button" data-press wire:click="collect({{ $booking['id'] }}, 'cash')">Cash</button>
                                <button type="button" data-press wire:click="collect({{ $booking['id'] }}, 'upi')">UPI</button>
                                <button type="button" data-press wire:click="collect({{ $booking['id'] }}, 'card')">Card</button>
                            </div>
                        </div>
                    @endif
                    <button type="button" class="vdb-cancel" wire:click="cancelBooking({{ $booking['id'] }})"
                            wire:confirm="Cancel {{ $booking['customer'] }}'s booking? The court opens up again.">
                        Cancel booking
                    </button>
                </div>
            </aside>
        </div>
        </template></div>
    @endif
</div>

<style>
    /* ── Design Tokens: Premium, Restrained, Sports-Authoritative ───────── */
    .vdb, .vdb-sheet-wrap {
        --ink: #0F172A;
        --ink2: #334155;
        --ink3: #64748B;
        --ink4: #94A3B8;
        --surface: #FFFFFF;
        --line: #E2E8F0;
        --line2: #F1F5F9;
        --blue: #1D4ED8;
        --blue2: #1E40AF;
        --blue-subtle: #EFF6FF;
        --blue-line: #BFDBFE;
        --green: #059669;
        --green-bg: #F0FDF4;
        --green-line: #BBF7D0;
        --amber: #D97706;
        --amber-bg: #FFFBEB;
        --amber-line: #FDE68A;
        --red: #E11D48;
        --red-bg: #FFF1F2;
        --red-line: #FECDD3;
        --ease: cubic-bezier(0.22, 0.9, 0.24, 1);
        --spring: cubic-bezier(0.18, 1.25, 0.4, 1);
        color: var(--ink);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        touch-action: manipulation;
    }

    .vdb {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding-bottom: max(20px, env(safe-area-inset-bottom, 0px));
    }
    .vdb button, .vdb-sheet-wrap button { font: inherit; cursor: pointer; -webkit-tap-highlight-color: transparent; }
    .vdb svg, .vdb-sheet-wrap svg { fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .vdb-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11.5px !important; color: var(--ink3); letter-spacing: .02em; word-break: break-all; }

    /* ── Venue + Day Header ─────────────────────────────────────────────── */
    .vdb-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        padding: 2px 2px;
    }
    .vdb-where {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-weight: 700;
        font-size: 14.5px;
        color: var(--ink);
    }
    .vdb-where svg { width: 17px; height: 17px; color: var(--blue); flex: none; }
    .vdb-where select {
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 6px 28px 6px 10px;
        font-weight: 700;
        font-size: 14px;
        background-color: #fff;
        color: var(--ink);
        box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
    }
    .vdb-venue-name { font-weight: 750; font-size: 15px; letter-spacing: -.2px; }
    .vdb-top-end {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .vdb-dayname {
        font-size: 13px;
        font-weight: 600;
        color: var(--ink3);
        letter-spacing: -.1px;
    }
    .vdb-day-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #fff;
        border: 1px solid var(--line);
        color: var(--ink2);
        font-size: 12px;
        font-weight: 650;
        padding: 5px 10px;
        border-radius: 99px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
        transition: transform .12s var(--ease), background-color .15s, border-color .15s;
    }
    .vdb-day-status:active { transform: scale(.95); }
    .vdb-day-status.is-closed {
        background: var(--red-bg);
        border-color: var(--red-line);
        color: var(--red);
    }
    .vdb-status-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--green);
        flex: none;
    }
    .vdb-day-status.is-closed .vdb-status-dot { background: var(--red); }

    /* ── Date Navigator Strip ────────────────────────────────────────── */
    .vdb-strip-wrap {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .vdb-arrow {
        flex: none;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: 1px solid var(--line);
        background: #fff;
        display: grid;
        place-items: center;
        color: var(--ink2);
        box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
        transition: transform .12s var(--ease), background-color .15s;
    }
    .vdb-arrow:active { transform: scale(.9); }
    .vdb-arrow svg { width: 15px; height: 15px; stroke-width: 2.2; }
    .vdb-strip {
        flex: 1;
        display: flex;
        gap: 6px;
        overflow-x: auto;
        scroll-behavior: smooth;
        scrollbar-width: none;
        padding: 2px 1px 4px;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
    }
    .vdb-strip::-webkit-scrollbar { display: none; }
    .vdb-day {
        position: relative;
        flex: none;
        width: 66px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        padding: 8px 3px 9px;
        border-radius: 14px;
        border: 1px solid var(--line);
        background: #fff;
        scroll-snap-align: center;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
        transition: background-color .18s var(--ease), border-color .18s, transform .12s var(--ease), color .18s;
    }
    .vdb-day:active { transform: scale(.94); }
    .vdb-day-dow {
        font-size: 10px;
        font-weight: 750;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--ink3);
    }
    .vdb-day-num {
        font-size: 19px;
        font-weight: 800;
        letter-spacing: -.03em;
        line-height: 1.1;
        font-variant-numeric: tabular-nums;
        color: var(--ink);
    }
    .vdb-day-count {
        font-size: 10px;
        font-weight: 600;
        color: var(--ink4);
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .vdb-today-indicator {
        position: absolute;
        bottom: 3px;
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: var(--blue);
    }
    .vdb-day.is-today:not(.is-on) .vdb-day-dow { color: var(--blue); }
    /* Authoritative, handcrafted active day state */
    .vdb-day.is-on {
        background: var(--ink);
        border-color: var(--ink);
        color: #fff;
        box-shadow: 0 8px 20px -8px rgba(15, 23, 42, .45);
    }
    .vdb-day.is-on .vdb-day-dow { color: rgba(255, 255, 255, .7); }
    .vdb-day.is-on .vdb-day-num { color: #fff; }
    .vdb-day.is-on .vdb-day-count { color: rgba(255, 255, 255, .82); font-weight: 700; }
    .vdb-day.is-on .vdb-today-indicator { background: #60A5FA; }

    /* ── Executive Day KPI Card ───────────────────────────────────────── */
    .vdb-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 16px 18px 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .04), 0 4px 12px -2px rgba(15, 23, 42, .02);
    }
    .vdb-card-kpi { display: flex; flex-direction: column; gap: 10px; }
    .vdb-kpi-main { display: flex; flex-direction: column; gap: 8px; }
    .vdb-kpi-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
    .vdb-lab {
        font-size: 10px;
        font-weight: 750;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--ink3);
    }
    .vdb-big {
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -.03em;
        line-height: 1.1;
        margin-top: 1px;
        font-variant-numeric: tabular-nums;
        color: var(--ink);
    }
    .vdb-occ-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 10px;
        border-radius: 99px;
        background: var(--blue-subtle);
        border: 1px solid var(--blue-line);
        color: var(--blue2);
    }
    .vdb-occ-pct { font-size: 13.5px; font-weight: 800; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
    .vdb-occ-tag { font-size: 10px; font-weight: 750; text-transform: uppercase; letter-spacing: .05em; opacity: .85; }
    .vdb-bar-section { display: flex; flex-direction: column; gap: 6px; }
    .vdb-bar {
        height: 5px;
        border-radius: 99px;
        background: var(--line2);
        overflow: hidden;
    }
    .vdb-bar i {
        display: block;
        height: 100%;
        border-radius: 99px;
        background: var(--green);
        transform-origin: left;
        animation: vdb-grow .7s var(--ease) .08s both;
    }
    @keyframes vdb-grow { from { transform: scaleX(0); } }
    .vdb-collected-info {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 12px;
        color: var(--ink3);
    }
    .vdb-collected-sum b { color: var(--ink); font-weight: 750; font-variant-numeric: tabular-nums; }
    .vdb-collected-sum b.is-in { color: var(--green); }
    .vdb-split-details { font-size: 11.5px; color: var(--ink4); }

    /* Due Alert Banner */
    .vdb-due {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        margin-top: 10px;
        padding: 9px 12px;
        border-radius: 12px;
        border: 1px solid var(--red-line);
        background: var(--red-bg);
        text-align: left;
        transition: transform .12s var(--ease), background-color .15s;
    }
    .vdb-due:active { transform: scale(.985); }
    .vdb-due-beacon { width: 7px; height: 7px; border-radius: 50%; background: var(--red); flex: none; }
    .vdb-due-label { flex: 1; display: flex; flex-direction: column; }
    .vdb-due-label b { color: #BE123C; font-size: 13px; font-weight: 750; font-variant-numeric: tabular-nums; }
    .vdb-due-label em { font-style: normal; color: #E11D48; opacity: .85; font-size: 11.5px; }
    .vdb-due-action { color: #BE123C; font-size: 12px; font-weight: 700; }
    .vdb-due.is-on { background: #FFE4E6; }

    /* Compact 3-Stat Metric Row */
    .vdb-counts {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px solid var(--line2);
    }
    .vdb-count-box {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1px;
    }
    .vdb-count-box + .vdb-count-box { border-left: 1px solid var(--line2); }
    .vdb-count-box b { font-size: 18px; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--ink); }
    .vdb-count-box b.is-blue { color: var(--blue); }
    .vdb-count-box b.is-green { color: var(--green); }
    .vdb-count-box span { font-size: 11px; font-weight: 600; color: var(--ink3); display: inline-flex; align-items: center; gap: 4px; }
    .vdb-open-pip { width: 5px; height: 5px; border-radius: 50%; background: var(--green); }

    .vdb-closed-row {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 12px;
        background: var(--red-bg);
        border: 1px solid var(--red-line);
        color: var(--red);
        font-size: 12.5px;
        font-weight: 650;
        margin-bottom: 12px;
    }
    .vdb-closed-row svg { width: 16px; height: 16px; flex: none; }
    .vdb-closed-row span { flex: 1; }
    .vdb-closed-row button { border: 0; background: none; color: var(--blue); font-weight: 700; padding: 4px 8px; border-radius: 6px; }

    /* ── Earlier Completed Slots Divider ─────────────────────────────── */
    .vdb-grid-block { display: flex; flex-direction: column; gap: 8px; }
    .vdb-earlier {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        align-self: flex-start;
        border: 1px solid var(--line);
        background: #fff;
        border-radius: 99px;
        padding: 5px 12px 5px 9px;
        font-size: 12px;
        font-weight: 600;
        color: var(--ink3);
        box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
        transition: transform .12s var(--ease), border-color .15s;
    }
    .vdb-earlier:active { transform: scale(.95); }
    .vdb-earlier svg { width: 14px; height: 14px; }
    .vdb-earlier b { color: var(--blue); font-weight: 700; }

    /* ── Courts × Times Grid ─────────────────────────────────────────── */
    .vdb-grid-card {
        width: fit-content;
        max-width: 100%;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 18px;
        overflow: auto;
        max-height: calc(100vh - 140px);
        box-shadow: 0 1px 3px rgba(15, 23, 42, .04), 0 4px 12px -2px rgba(15, 23, 42, .02);
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }
    .vdb-grid {
        display: grid;
        grid-template-columns: 74px repeat(var(--cols), minmax(132px, 190px));
        gap: 8px;
        padding: 0 10px 12px 0;
        min-width: min-content;
    }
    .vdb-corner, .vdb-court {
        position: sticky;
        top: 0;
        z-index: 3;
        background: #F8FAFC;
        padding: 10px 8px;
        border-bottom: 1px solid var(--line);
    }
    .vdb-corner {
        left: 0;
        z-index: 4;
        font-size: 9.5px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--ink3);
        display: flex;
        align-items: flex-end;
        justify-content: center;
        border-right: 1px solid var(--line2);
    }
    .vdb-court {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 2px;
    }
    .vdb-court b {
        font-size: 13px;
        font-weight: 750;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
        color: var(--ink);
    }
    .vdb-court span {
        font-size: 9.5px;
        font-weight: 650;
        color: var(--ink3);
        padding: 1px 6px;
        border-radius: 6px;
        background: #EEF2F7;
        text-transform: capitalize;
    }

    /* Time Gutter Column */
    .vdb-time {
        position: sticky;
        left: 0;
        z-index: 2;
        background: #fff;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 750;
        font-variant-numeric: tabular-nums;
        color: var(--ink2);
        border-right: 1px solid var(--line2);
    }
    .vdb-time em {
        font-style: normal;
        margin-top: 3px;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #fff;
        background: var(--blue);
        padding: 1px 6px;
        border-radius: 99px;
    }
    .vdb-time.is-now {
        color: var(--blue);
        box-shadow: inset 3px 0 0 var(--blue);
        background: #F8FAFC;
    }
    .vdb-time.is-past { color: var(--ink4); }

    /* ── Slot Cell Redesign: Handcrafted State Machine ───────────────── */
    .vdb-cell {
        position: relative;
        height: 84px;
        border-radius: 12px;
        border: 1px solid var(--line);
        background: #fff;
        padding: 8px 10px;
        text-align: left;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-width: 0;
        user-select: none;
        transition: transform .12s var(--ease), box-shadow .15s var(--ease), border-color .15s;
    }
    button.vdb-cell:active { transform: scale(.965); }

    /* State 1: Open / Available Slot */
    .vdb-cell.is-open {
        background: #FFFFFF;
        border-color: rgba(5, 150, 105, .3);
        box-shadow: 0 1px 2px rgba(15, 23, 42, .02);
    }
    .vdb-cell.is-open:hover {
        border-color: var(--green);
        box-shadow: 0 4px 12px -3px rgba(5, 150, 105, .15);
    }
    .vdb-open {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 700;
        color: var(--green);
    }
    .vdb-open i { width: 5px; height: 5px; border-radius: 50%; background: var(--green); }
    .vdb-open.is-peak { color: var(--amber); }
    .vdb-open.is-peak i { background: var(--amber); }
    .vdb-price {
        font-size: 18px;
        font-weight: 800;
        letter-spacing: -.02em;
        font-variant-numeric: tabular-nums;
        color: var(--ink);
    }
    /* Integrated tactile touch target */
    .vdb-plus {
        position: absolute;
        right: 8px;
        bottom: 8px;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: var(--blue);
        color: #fff;
        display: grid;
        place-items: center;
        box-shadow: 0 4px 10px -3px rgba(29, 78, 216, .7);
        transition: transform .15s var(--spring);
    }
    .vdb-plus svg { width: 13px; height: 13px; stroke-width: 2.5; }
    button.vdb-cell.is-open:hover .vdb-plus { transform: scale(1.1); }

    /* State 2: Booked (Walk-in vs Online) */
    .vdb-cell.is-booked {
        background: #F0F7FF;
        border-color: #BAE6FD;
    }
    .vdb-cell.is-walkin {
        background: #F0F9FF;
        border-color: #BAE6FD;
    }
    .vdb-kicker {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--blue2);
    }
    .vdb-cell.is-walkin .vdb-kicker { color: #0284C7; }
    .vdb-in {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        font-style: normal;
        color: var(--green);
        background: #DCFCE7;
        border-radius: 4px;
        padding: 1px 4px;
        letter-spacing: .02em;
        font-size: 8.5px;
    }
    .vdb-in svg { width: 9px; height: 9px; stroke-width: 2.5; }
    .vdb-name {
        font-size: 12.5px;
        font-weight: 750;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: var(--ink);
    }
    .vdb-amt {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 4px;
        font-size: 11.5px;
        font-weight: 650;
        color: var(--ink3);
        font-variant-numeric: tabular-nums;
    }
    .vdb-amt em { font-style: normal; font-size: 10px; font-weight: 750; }
    .vdb-amt em.is-due { color: var(--red); }
    .vdb-amt em.is-paid { color: var(--green); }

    /* State 3: Held / In Checkout */
    .vdb-cell.is-held {
        background: #FFFBEB;
        border-color: #FDE68A;
        align-items: center;
        justify-content: center;
        gap: 2px;
        color: var(--amber);
    }
    .vdb-cell.is-held svg { width: 14px; height: 14px; }
    .vdb-cell.is-held b { font-size: 9.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .vdb-cell.is-held span { font-size: 10px; color: var(--ink3); }

    /* State 4: Blocked / Maintenance */
    .vdb-cell.is-blocked {
        background: #F1F5F9;
        border: 1px solid #CBD5E1;
        align-items: center;
        justify-content: center;
        gap: 1px;
        color: #475569;
        text-align: center;
    }
    .vdb-cell.is-blocked svg { width: 14px; height: 14px; }
    .vdb-cell.is-blocked b { font-size: 9.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .vdb-cell.is-blocked span { font-size: 10px; color: var(--ink3); max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* Inactive / Past Slots */
    .vdb-cell.is-off {
        background: #F8FAFC;
        border: 1px dashed var(--line);
        align-items: center;
        justify-content: center;
    }
    .vdb-cell.is-off span { font-size: 10px; font-weight: 650; color: var(--ink4); }
    .vdb-cell.is-gone { background: #FAFBFD; border-color: var(--line2); align-items: center; justify-content: center; color: #CBD5E1; }
    .vdb-cell.is-past.is-booked { opacity: .55; }
    .vdb-grid-card.is-due .vdb-cell:not(.is-owing) { opacity: .25; }
    .vdb-grid-card.is-due .vdb-cell.is-owing { border-color: #FDA4AF; box-shadow: 0 0 0 2px #FFE4E6; }
    .vdb-grid-card.hide-past [data-past] { display: none; }
    .vdb-hint { font-size: 11.5px; color: var(--ink4); text-align: center; margin: 4px 0 0; }

    /* ── Action Sheets: Handcrafted iOS / Mobile Modal ───────────────── */
    .vdb-sheet-wrap { position: fixed; inset: 0; z-index: 60; }
    .vdb-scrim { position: absolute; inset: 0; background: rgba(15, 23, 42, .36); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
    .vdb-sheet {
        position: absolute;
        top: 12px;
        right: 12px;
        bottom: 12px;
        width: min(430px, calc(100vw - 24px));
        background: #fff;
        border-radius: 22px;
        box-shadow: 0 24px 60px -15px rgba(15, 23, 42, .35);
        display: flex;
        flex-direction: column;
        overflow: auto;
    }
    .vdb-sheet-handle { display: none; width: 36px; height: 4px; border-radius: 99px; background: #CBD5E1; margin: 10px auto 4px; flex: none; }
    .vdb-sheet-in { transition: transform .26s var(--ease), opacity .2s; }
    .vdb-sheet-out { transition: transform .18s ease-in, opacity .16s; }
    .vdb-sheet-from { transform: translateX(24px); opacity: 0; }
    .vdb-sheet-to { transform: none; opacity: 1; }
    .vdb-sheet-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 20px 14px;
        border-bottom: 1px solid var(--line2);
    }
    .vdb-sheet-head h3 { font-size: 17px; font-weight: 750; letter-spacing: -.02em; margin: 2px 0 0; color: var(--ink); }
    .vdb-sheet-head p, .vdb-sheet-head a { font-size: 12.5px; color: var(--ink3); margin: 2px 0 0; display: block; }
    .vdb-sheet-head a { color: var(--blue); font-weight: 600; font-variant-numeric: tabular-nums; }
    .vdb-x {
        flex: none;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 0;
        background: #F1F5F9;
        display: grid;
        place-items: center;
        color: var(--ink2);
        transition: transform .12s;
    }
    .vdb-x:active { transform: scale(.9); }
    .vdb-x svg { width: 14px; height: 14px; stroke-width: 2.2; }
    .vdb-who { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .vdb-av { width: 42px; height: 42px; border-radius: 50%; flex: none; display: grid; place-items: center; color: #fff; font-weight: 750; font-size: 16px; }
    .vdb-form { padding: 16px 20px 20px; display: flex; flex-direction: column; gap: 14px; flex: 1; }
    .vdb-field { display: flex; flex-direction: column; gap: 6px; }
    .vdb-field > span { font-size: 12px; font-weight: 700; color: var(--ink2); }
    .vdb-field > span small { font-weight: 500; color: var(--ink4); margin-left: 4px; }
    .vdb-field input {
        height: 44px;
        border: 1px solid #CBD5E1;
        border-radius: 12px;
        padding: 0 12px;
        font-size: 14.5px;
        color: var(--ink);
        background: #fff;
        width: 100%;
        transition: border-color .15s, box-shadow .15s;
    }
    .vdb-field input:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(29, 78, 216, .14); }
    .vdb-field em { font-style: normal; font-size: 12px; color: var(--red); }
    .vdb-phone { display: flex; align-items: center; border: 1px solid #CBD5E1; border-radius: 12px; overflow: hidden; background: #fff; }
    .vdb-phone:focus-within { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(29, 78, 216, .14); }
    .vdb-phone b { padding: 0 10px 0 12px; font-size: 14px; font-weight: 700; color: var(--ink3); border-right: 1px solid var(--line); }
    .vdb-phone input { border: 0; box-shadow: none !important; letter-spacing: .02em; font-variant-numeric: tabular-nums; }
    .vdb-modes { display: flex; gap: 4px; padding: 4px; border-radius: 12px; background: #F1F5F9; }
    .vdb-modes button {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 40px;
        border: 0;
        border-radius: 9px;
        background: none;
        font-size: 13px;
        font-weight: 700;
        color: var(--ink3);
        transition: background-color .15s, color .15s, box-shadow .15s;
    }
    .vdb-modes button svg { width: 15px; height: 15px; }
    .vdb-modes button.is-on { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(15, 23, 42, .08); }
    .vdb-dur { display: grid; grid-template-columns: repeat(auto-fit, minmax(70px, 1fr)); gap: 6px; }
    .vdb-dur button {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        min-height: 52px;
        padding: 6px 4px;
        border-radius: 11px;
        border: 1px solid var(--line);
        background: #fff;
        color: var(--ink);
        transition: background-color .15s, border-color .15s, color .15s, transform .1s var(--ease);
    }
    .vdb-dur button:active { transform: scale(.96); }
    .vdb-dur button b { font-size: 13.5px; font-weight: 750; }
    .vdb-dur button small { font-size: 10px; color: var(--ink3); font-variant-numeric: tabular-nums; }
    .vdb-dur button.is-on { background: var(--blue-subtle); border-color: var(--blue); color: var(--blue2); }
    .vdb-dur button.is-off { opacity: .45; cursor: not-allowed; text-decoration: line-through; }
    .vdb-seg { display: flex; gap: 6px; flex-wrap: wrap; }
    .vdb-seg button {
        flex: 1;
        min-height: 40px;
        padding: 0 12px;
        border-radius: 10px;
        border: 1px solid var(--line);
        background: #fff;
        font-size: 13px;
        font-weight: 650;
        color: var(--ink2);
        transition: background-color .15s, border-color .15s, color .15s, transform .1s;
    }
    .vdb-seg button:active { transform: scale(.96); }
    .vdb-seg button.is-on { background: var(--blue-subtle); border-color: var(--blue); color: var(--blue2); font-weight: 750; }
    .vdb-sheet-foot { margin-top: auto; display: flex; align-items: center; gap: 12px; padding-top: 14px; border-top: 1px solid var(--line2); }
    .vdb-total { flex: 1; display: flex; flex-direction: column; }
    .vdb-total span { font-size: 11.5px; color: var(--ink3); }
    .vdb-total b { font-size: 21px; font-weight: 800; letter-spacing: -.03em; font-variant-numeric: tabular-nums; color: var(--ink); }
    .vdb-primary {
        min-height: 46px;
        padding: 0 20px;
        border: 0;
        border-radius: 12px;
        background: var(--blue);
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        box-shadow: 0 4px 14px -3px rgba(29, 78, 216, .6);
        transition: background-color .15s, transform .1s var(--ease);
    }
    .vdb-primary:active { transform: scale(.96); }
    .vdb-primary:hover { background: var(--blue2); }
    .vdb-primary[disabled] { opacity: .65; cursor: not-allowed; }
    .vdb-facts { margin: 0; padding: 6px 20px; }
    .vdb-facts div { display: flex; justify-content: space-between; gap: 14px; padding: 9px 0; border-bottom: 1px solid var(--line2); }
    .vdb-facts dt { font-size: 12.5px; color: var(--ink3); }
    .vdb-facts dd { margin: 0; font-size: 13px; font-weight: 650; text-align: right; font-variant-numeric: tabular-nums; color: var(--ink); }
    .vdb-facts dd.is-in { color: var(--green); font-weight: 750; }
    .vdb-facts dd.is-due { color: var(--red); font-weight: 750; }
    .vdb-actions { padding: 12px 20px 20px; display: flex; flex-direction: column; gap: 10px; margin-top: auto; }
    .vdb-actions .vdb-primary { width: 100%; }
    .vdb-collect { display: flex; flex-direction: column; gap: 6px; }
    .vdb-collect > span { font-size: 12px; font-weight: 700; color: var(--ink2); }
    .vdb-cancel { border: 0; background: none; color: var(--red); font-size: 13px; font-weight: 650; padding: 9px; border-radius: 9px; }
    .vdb-cancel:hover { background: var(--red-bg); }

    /* Empty states */
    .vdb-empty {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 36px 20px;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
    }
    .vdb-empty img, .vdb-illo { width: 160px; height: auto; margin-bottom: 8px; }
    .vdb-empty h3 { font-size: 16px; font-weight: 750; margin: 0; color: var(--ink); }
    .vdb-empty p { font-size: 13px; color: var(--ink3); max-width: 44ch; margin: 0 0 10px; line-height: 1.5; }

    /* ── Mobile-First Responsive Breakpoint ───────────────────────────── */
    @media (max-width: 767px) {
        .vdb { gap: 10px; padding-bottom: calc(96px + env(safe-area-inset-bottom, 0px)); }
        .vdb-top { flex-direction: column; align-items: stretch; gap: 6px; }
        .vdb-top-end { justify-content: space-between; }
        .vdb-card { padding: 14px 15px 12px; border-radius: 16px; }
        .vdb-big { font-size: 24px; }
        .vdb-grid { grid-template-columns: 66px repeat(var(--cols), minmax(126px, 1fr)); }
        .vdb-arrow { display: none; }
        .vdb-hint { display: none; }
        .vdb-sheet {
            top: auto;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            max-height: 88vh;
            border-radius: 24px 24px 0 0;
            padding-bottom: max(16px, env(safe-area-inset-bottom, 0px));
        }
        .vdb-sheet-handle { display: block; }
        .vdb-sheet-from { transform: translateY(100%); }
    }

    @media (prefers-reduced-motion: reduce) {
        .vdb * { animation: none !important; transition: none !important; }
    }

    /* ── Dark Mode Support ────────────────────────────────────────────── */
    .dark .vdb, .dark .vdb-sheet-wrap {
        --ink: #F1F5F9;
        --ink2: #CBD5E1;
        --ink3: #94A3B8;
        --ink4: #64748B;
        --line: #1E293B;
        --line2: #1E293B;
        --surface: #0F172A;
        --blue-subtle: #1E293B;
        --blue-line: #1D4ED8;
    }
    .dark .vdb-card, .dark .vdb-grid-card, .dark .vdb-empty, .dark .vdb-day,
    .dark .vdb-arrow, .dark .vdb-sheet, .dark .vdb-time, .dark .vdb-cell, .dark .vdb-where select {
        background-color: #0F172A;
    }
    .dark .vdb-corner, .dark .vdb-court { background: #0B1120; }
    .dark .vdb-cell.is-open { background: #0F172A; border-color: rgba(5, 150, 105, .3); }
    .dark .vdb-cell.is-booked, .dark .vdb-cell.is-walkin { background: #13233D; border-color: #1E3A8A; }
    .dark .vdb-modes, .dark .vdb-x { background: #1E293B; }
    .dark .vdb-modes button.is-on { background: #0F172A; color: #fff; }
</style>
</x-filament-panels::page>
