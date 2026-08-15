@php
    $branch = $this->branch();
    $noun = $this->noun();
    $nounPlural = $this->noun(true);
@endphp

<x-filament-panels::page>
    @if ($branch === null)
        {{-- A desk is a physical place. Rather than guess which floor you are
             standing on, ask — seating someone at the wrong outlet is worse than
             one extra click. --}}
        <div class="hrn-desk-pick">
            <h2>Which floor are you on?</h2>
            <p>A desk belongs to one branch. Pick the one you're standing at — the topbar switcher changes it later.</p>
            <div class="hrn-desk-pick-row">
                @foreach ($this->branches() as $b)
                    <form method="POST" action="{{ route('partner.branch.switch') }}">
                        @csrf
                        <button type="submit" name="venue_id" value="{{ $b->id }}" class="hrn-desk-pick-btn">
                            <span class="hrn-desk-pick-name">{{ $b->branchName() }}</span>
                            <span class="hrn-desk-pick-sub">{{ $b->branch_code ?: $b->city ?: $b->location }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    @else
        @php($s = $this->summary())

        <div class="hrn-desk-strip">
            <div class="hrn-desk-stat">
                <span class="hrn-desk-val hrn-free">{{ $s['free'] }}</span>
                <span class="hrn-desk-lbl">free now</span>
            </div>
            <div class="hrn-desk-stat">
                <span class="hrn-desk-val">{{ $s['busy'] }}</span>
                <span class="hrn-desk-lbl">occupied</span>
            </div>
            <div class="hrn-desk-stat">
                <span class="hrn-desk-val">{{ $s['upcoming'] }}</span>
                <span class="hrn-desk-lbl">still to come</span>
            </div>
            <div class="hrn-desk-actions">
                <button type="button" class="hrn-ahead-btn" wire:click="openReserve">Book ahead</button>
                <span class="hrn-desk-branch">{{ $branch->branchName() }}</span>
            </div>
        </div>

        <div class="hrn-desk-grid">
            @forelse ($this->floor() as $u)
                <div @class(['hrn-unit', 'is-busy' => $u['busy']])>
                    <div class="hrn-unit-top">
                        <span class="hrn-unit-name">{{ $u['name'] }}</span>
                        <span @class(['hrn-pill', 'is-busy' => $u['busy']])>
                            {{ $u['busy'] ? 'Busy' : 'Free' }}
                        </span>
                    </div>

                    <div class="hrn-unit-meta">
                        {{ $u['kind'] }}@if ($u['seats']) · {{ $u['seats'] }}@endif · ₹{{ number_format($u['rate']) }}/hr
                    </div>

                    <div class="hrn-unit-state">
                        @if ($u['busy'])
                            {{ $u['guest'] ?: 'In use' }}@if ($u['busy_until']) · until {{ $u['busy_until'] }}@endif
                        @elseif ($u['next_at'])
                            Free — booked from {{ $u['next_at'] }}
                        @else
                            Free all evening
                        @endif
                    </div>

                    @if (! $u['busy'])
                        <button type="button" wire:click="openSeat({{ $u['id'] }})" class="hrn-seat-btn">
                            Seat a walk-in
                        </button>
                    @endif
                </div>
            @empty
                <div class="hrn-desk-empty">
                    No {{ $nounPlural }} set up at {{ $branch->branchName() }} yet.
                    Add them under the branch's {{ $nounPlural }} to run the floor from here.
                </div>
            @endforelse
        </div>

        @if ($this->upcoming())
            <div class="hrn-desk-next">
                <h3>Expected today</h3>
                <ul>
                    @foreach ($this->upcoming() as $b)
                        <li @class(['is-in' => $b['arrived']])>
                            <span class="hrn-next-at">{{ $b['at'] }}</span>
                            <span class="hrn-next-who">
                                {{ $b['who'] }}
                                @if ($b['where']) <span class="hrn-next-where">· {{ $b['where'] }}</span> @endif
                            </span>
                            <span class="hrn-next-amt">
                                ₹{{ number_format($b['amount']) }}
                                @unless ($b['paid']) <span class="hrn-next-due">due</span> @endunless
                            </span>
                            @if ($b['arrived'])
                                {{-- Never colour alone: the state is a word. --}}
                                <span class="hrn-next-in">Here</span>
                            @elseif ($this->canCheckIn())
                                <button type="button" class="hrn-in-btn" wire:click="checkIn({{ $b['id'] }})">
                                    Arrived
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($this->reserving)
            @php($free = $this->freeUnitsAt())
            <div class="hrn-sheet-scrim" wire:click="closeReserve"></div>
            <div class="hrn-sheet" role="dialog" aria-modal="true" aria-label="Book ahead">
                <h3>Book ahead</h3>
                <p class="hrn-sheet-sub">Later today. We'll find a {{ $noun }} that fits.</p>

                <div class="hrn-sheet-row">
                    <div>
                        <label>Time</label>
                        <input type="text" wire:model.live="reserveAt" placeholder="7:30 PM">
                    </div>
                    <div>
                        <label>Hours</label>
                        <input type="number" min="1" max="12" wire:model.live="hours">
                    </div>
                </div>

                <label>Party size <span>optional</span></label>
                <input type="number" min="1" wire:model.live="partySize" placeholder="How many?">

                {{-- Live: the desk sees what's actually open before committing. --}}
                <div class="hrn-avail">
                    @if ($free)
                        <span class="hrn-avail-ok">{{ count($free) }} free</span>
                        <select wire:model="reserveCourtId" aria-label="Which {{ $noun }}">
                            <option value="">Best fit — {{ $free[0]['label'] }}</option>
                            @foreach ($free as $u)
                                <option value="{{ $u['id'] }}">{{ $u['label'] }}</option>
                            @endforeach
                        </select>
                    @else
                        <span class="hrn-avail-no">Nothing free for that window</span>
                    @endif
                </div>

                <label>Name <span>optional</span></label>
                <input type="text" wire:model="guestName" placeholder="Who's it for?">

                <label>Phone <span>optional</span></label>
                <input type="tel" wire:model="guestPhone" placeholder="10-digit number">

                <div class="hrn-sheet-actions">
                    <button type="button" class="hrn-btn-ghost" wire:click="closeReserve">Cancel</button>
                    <button type="button" class="hrn-btn-go" wire:click="reserve"
                            wire:loading.attr="disabled" @disabled(! $free)>
                        Book it
                    </button>
                </div>
            </div>
        @endif

        @if ($this->seatingCourtId)
            <div class="hrn-sheet-scrim" wire:click="closeSeat"></div>
            <div class="hrn-sheet" role="dialog" aria-modal="true" aria-label="Seat a walk-in">
                <h3>Seat a walk-in</h3>
                <p class="hrn-sheet-sub">Starts now. Books the {{ $noun }} for the hours you pick.</p>

                <label>Name <span>optional</span></label>
                <input type="text" wire:model="guestName" placeholder="Who's it for?">

                <label>Phone <span>optional</span></label>
                <input type="tel" wire:model="guestPhone" placeholder="10-digit number">

                <div class="hrn-sheet-row">
                    <div>
                        <label>Party size <span>optional</span></label>
                        <input type="number" min="1" wire:model="partySize" placeholder="—">
                    </div>
                    <div>
                        <label>Hours</label>
                        <input type="number" min="1" max="12" wire:model="hours">
                    </div>
                </div>

                <div class="hrn-sheet-actions">
                    <button type="button" class="hrn-btn-ghost" wire:click="closeSeat">Cancel</button>
                    <button type="button" class="hrn-btn-go" wire:click="seat" wire:loading.attr="disabled">
                        Seat them
                    </button>
                </div>
            </div>
        @endif
    @endif

    <style>
        .hrn-desk-pick{background:#fff;border-radius:18px;padding:26px 22px;text-align:center;
            box-shadow:0 1px 2px rgba(15,23,42,.04),0 0 0 1px #e9edf4;}
        .hrn-desk-pick h2{font-size:17px;font-weight:700;color:#0b1220;margin:0 0 4px;}
        .hrn-desk-pick p{font-size:13px;color:#6b7688;margin:0 0 16px;}
        .hrn-desk-pick-row{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;}
        .hrn-desk-pick-btn{display:flex;flex-direction:column;gap:2px;padding:12px 18px;border:0;
            border-radius:13px;background:#f2f6fd;box-shadow:inset 0 0 0 1px #e2e9f5;cursor:pointer;}
        .hrn-desk-pick-btn:hover{background:#e9f0fc;}
        .hrn-desk-pick-name{font-size:14px;font-weight:700;color:#1e3a6b;}
        .hrn-desk-pick-sub{font-size:11px;color:#6b7688;}

        .hrn-desk-strip{display:flex;align-items:center;gap:22px;flex-wrap:wrap;background:#fff;
            border-radius:18px;padding:14px 18px;margin-bottom:14px;
            box-shadow:0 1px 2px rgba(15,23,42,.04),0 0 0 1px #e9edf4;}
        .hrn-desk-stat{display:flex;flex-direction:column;}
        .hrn-desk-val{font-size:22px;font-weight:800;color:#0b1220;line-height:1.1;
            font-variant-numeric:tabular-nums;}
        .hrn-desk-val.hrn-free{color:#16803C;}
        .hrn-desk-lbl{font-size:11px;color:#6b7688;text-transform:uppercase;letter-spacing:.04em;}
        .hrn-desk-actions{margin-left:auto;display:flex;align-items:center;gap:10px;}
        .hrn-desk-branch{font-size:12.5px;font-weight:700;color:#1e3a6b;
            background:#f2f6fd;border-radius:999px;padding:5px 12px;}
        .hrn-ahead-btn{padding:8px 14px;border:0;border-radius:10px;cursor:pointer;
            background:#0A66FF;color:#fff;font-size:12.5px;font-weight:700;}
        .hrn-ahead-btn:hover{background:#1D4ED8;}

        .hrn-in-btn{padding:5px 11px;border:0;border-radius:9px;cursor:pointer;
            background:#16803C;color:#fff;font-size:11.5px;font-weight:700;}
        .hrn-in-btn:hover{background:#12662f;}
        .hrn-next-in{font-size:11px;font-weight:700;color:#16803C;
            background:#e7f6ec;border-radius:999px;padding:3px 9px;}
        .hrn-desk-next li.is-in .hrn-next-who{color:#6b7688;}

        .hrn-avail{display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap;}
        .hrn-avail select{flex:1;min-width:140px;padding:8px 10px;border-radius:10px;border:0;
            font-size:13px;background:#f5f7fb;box-shadow:inset 0 0 0 1px #e2e8f0;color:#0b1220;}
        .hrn-avail-ok{font-size:11.5px;font-weight:700;color:#16803C;
            background:#e7f6ec;border-radius:999px;padding:4px 10px;white-space:nowrap;}
        .hrn-avail-no{font-size:12px;font-weight:600;color:#b4530a;}

        .hrn-desk-grid{display:grid;gap:12px;
            grid-template-columns:repeat(auto-fill,minmax(min(100%,220px),1fr));}
        .hrn-unit{background:#fff;border-radius:16px;padding:14px;
            box-shadow:0 1px 2px rgba(15,23,42,.04),0 0 0 1px #e9edf4;
            display:flex;flex-direction:column;gap:6px;}
        .hrn-unit.is-busy{background:#fbfcfe;}
        .hrn-unit-top{display:flex;align-items:center;justify-content:space-between;gap:8px;}
        .hrn-unit-name{font-size:14.5px;font-weight:700;color:#0b1220;}
        /* Never colour alone — the pill says the word too. */
        .hrn-pill{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;
            padding:3px 8px;border-radius:999px;background:#e7f6ec;color:#16803C;}
        .hrn-pill.is-busy{background:#f1f4f9;color:#6b7688;}
        .hrn-unit-meta{font-size:11.5px;color:#6b7688;}
        .hrn-unit-state{font-size:12.5px;color:#3d4759;min-height:18px;}
        .hrn-seat-btn{margin-top:4px;padding:8px 10px;border:0;border-radius:10px;cursor:pointer;
            background:#0A66FF;color:#fff;font-size:12.5px;font-weight:700;}
        .hrn-seat-btn:hover{background:#1D4ED8;}
        .hrn-desk-empty{grid-column:1/-1;background:#fff;border-radius:16px;padding:22px;
            text-align:center;font-size:13px;color:#6b7688;box-shadow:0 0 0 1px #e9edf4;}

        .hrn-desk-next{margin-top:16px;background:#fff;border-radius:18px;padding:14px 18px;
            box-shadow:0 1px 2px rgba(15,23,42,.04),0 0 0 1px #e9edf4;}
        .hrn-desk-next h3{font-size:13px;font-weight:700;color:#0b1220;margin:0 0 8px;}
        .hrn-desk-next ul{list-style:none;margin:0;padding:0;}
        .hrn-desk-next li{display:flex;align-items:center;gap:10px;padding:7px 0;
            border-bottom:1px solid #f1f4f9;font-size:13px;}
        .hrn-desk-next li:last-child{border-bottom:0;}
        .hrn-next-at{font-weight:700;color:#0b1220;min-width:70px;font-variant-numeric:tabular-nums;}
        .hrn-next-who{flex:1;color:#3d4759;}
        .hrn-next-where{color:#6b7688;}
        .hrn-next-amt{font-weight:700;color:#0b1220;font-variant-numeric:tabular-nums;}
        .hrn-next-due{font-size:10.5px;font-weight:700;color:#b4530a;margin-left:4px;}

        .hrn-sheet-scrim{position:fixed;inset:0;background:rgba(15,23,42,.38);z-index:60;
            backdrop-filter:blur(2px);}
        .hrn-sheet{position:fixed;z-index:61;left:50%;top:50%;transform:translate(-50%,-50%);
            width:min(94vw,400px);background:#fff;border-radius:18px;padding:20px;
            box-shadow:0 24px 60px rgba(15,23,42,.28);}
        .hrn-sheet h3{font-size:16px;font-weight:700;color:#0b1220;margin:0;}
        .hrn-sheet-sub{font-size:12px;color:#6b7688;margin:2px 0 14px;}
        .hrn-sheet label{display:block;font-size:11.5px;font-weight:600;color:#3d4759;margin:10px 0 4px;}
        .hrn-sheet label span{font-weight:500;color:#98a2b3;}
        .hrn-sheet input{width:100%;padding:9px 11px;border-radius:10px;border:0;font-size:13.5px;
            background:#f5f7fb;box-shadow:inset 0 0 0 1px #e2e8f0;color:#0b1220;}
        .hrn-sheet input:focus{outline:3px solid rgba(10,102,255,.35);}
        .hrn-sheet-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
        .hrn-sheet-actions{display:flex;gap:8px;margin-top:16px;}
        .hrn-btn-ghost,.hrn-btn-go{flex:1;padding:10px;border:0;border-radius:11px;cursor:pointer;
            font-size:13px;font-weight:700;}
        .hrn-btn-ghost{background:#f1f4f9;color:#3d4759;}
        .hrn-btn-go{background:#16803C;color:#fff;}
        .hrn-btn-go:hover{background:#12662f;}
        .hrn-btn-go:disabled{opacity:.6;cursor:progress;}

        .dark .hrn-desk-pick,.dark .hrn-desk-strip,.dark .hrn-unit,.dark .hrn-desk-next,
        .dark .hrn-sheet,.dark .hrn-desk-empty{background:#151b26;box-shadow:0 0 0 1px #2a3446;}
        .dark .hrn-unit.is-busy{background:#131822;}
        .dark .hrn-desk-pick h2,.dark .hrn-desk-val,.dark .hrn-unit-name,.dark .hrn-next-at,
        .dark .hrn-next-amt,.dark .hrn-desk-next h3,.dark .hrn-sheet h3{color:#eef2f8;}
        .dark .hrn-desk-pick p,.dark .hrn-desk-lbl,.dark .hrn-unit-meta,.dark .hrn-next-where,
        .dark .hrn-desk-empty,.dark .hrn-sheet-sub{color:#9aa6b8;}
        .dark .hrn-unit-state,.dark .hrn-next-who{color:#c7d0dd;}
        .dark .hrn-desk-branch,.dark .hrn-desk-pick-btn{background:#1d2a44;box-shadow:inset 0 0 0 1px #2a3446;}
        .dark .hrn-desk-pick-name{color:#dbe6fb;}
        .dark .hrn-pill{background:#12321f;color:#5fd08a;}
        .dark .hrn-pill.is-busy{background:#232c3b;color:#9aa6b8;}
        .dark .hrn-desk-next li{border-bottom-color:#232c3b;}
        .dark .hrn-sheet input{background:#1c2432;box-shadow:inset 0 0 0 1px #2a3446;color:#eef2f8;}
        .dark .hrn-btn-ghost{background:#232c3b;color:#c7d0dd;}
        .dark .hrn-desk-val.hrn-free,.dark .hrn-next-in,.dark .hrn-avail-ok{color:#5fd08a;}
        .dark .hrn-next-in,.dark .hrn-avail-ok{background:#12321f;}
        .dark .hrn-avail select{background:#1c2432;box-shadow:inset 0 0 0 1px #2a3446;color:#eef2f8;}

        @media (prefers-reduced-motion:reduce){*{animation-duration:.001ms!important;transition-duration:.001ms!important;}}
    </style>
</x-filament-panels::page>
