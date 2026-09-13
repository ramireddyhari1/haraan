@php
    $branch = $this->branch();
    $noun = $this->noun();
    $nounPlural = $this->noun(true);
@endphp

<x-filament-panels::page>
    @if ($branch === null)
        {{-- A desk is a physical place. Pick the branch floor you are standing on. --}}
        <div class="hrn-desk-pick">
            <div class="hrn-desk-pick-ic">
                <x-filament::icon icon="heroicon-o-building-storefront" />
            </div>
            <h2>Which floor are you on?</h2>
            <p>Today's Desk is tied to a physical venue outlet. Select your floor location below.</p>
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
        @php $s = $this->summary(); @endphp

        <div x-data="{ floorTab: 'all', searchArrivals: '' }" class="hrn-desk-workspace">
            {{-- Top Command Bar: Telemetry & Actions --}}
            <div class="hrn-desk-strip">
                <div class="hrn-desk-stats-group">
                    <div class="hrn-desk-stat hrn-stat-free" :class="{ 'is-active': floorTab === 'free' }" @click="floorTab = (floorTab === 'free' ? 'all' : 'free')">
                        <span class="hrn-desk-val hrn-free">{{ $s['free'] }}</span>
                        <span class="hrn-desk-lbl">free now</span>
                    </div>
                    <div class="hrn-desk-stat hrn-stat-busy" :class="{ 'is-active': floorTab === 'busy' }" @click="floorTab = (floorTab === 'busy' ? 'all' : 'busy')">
                        <span class="hrn-desk-val">{{ $s['busy'] }}</span>
                        <span class="hrn-desk-lbl">Occupied</span>
                    </div>
                    <div class="hrn-desk-stat">
                        <span class="hrn-desk-val">{{ $s['upcoming'] }}</span>
                        <span class="hrn-desk-lbl">Expected</span>
                    </div>
                </div>

                {{-- Filter Tabs for Fast Floor Triage on Touch Screens --}}
                <div class="hrn-filter-tabs">
                    <button type="button" class="hrn-tab-pill" :class="{ 'active': floorTab === 'all' }" @click="floorTab = 'all'">
                        All {{ ucfirst($nounPlural) }} ({{ $s['total'] }})
                    </button>
                    <button type="button" class="hrn-tab-pill pill-free" :class="{ 'active': floorTab === 'free' }" @click="floorTab = 'free'">
                        ● Available ({{ $s['free'] }})
                    </button>
                    <button type="button" class="hrn-tab-pill pill-busy" :class="{ 'active': floorTab === 'busy' }" @click="floorTab = 'busy'">
                        ● Busy ({{ $s['busy'] }})
                    </button>
                </div>

                <div class="hrn-desk-actions">
                    <button type="button" class="hrn-ahead-btn" wire:click="openReserve">
                        <x-filament::icon icon="heroicon-m-calendar-days" />
                        <span>Book ahead</span>
                    </button>
                    <span class="hrn-desk-branch">
                        <x-filament::icon icon="heroicon-m-map-pin" />
                        {{ $branch->branchName() }}
                    </span>
                </div>
            </div>

            {{-- Floor Layout Grid --}}
            <div class="hrn-desk-grid">
                @forelse ($this->floor() as $u)
                    <div @class(['hrn-unit', 'is-busy' => $u['busy']])
                         x-show="floorTab === 'all' || (floorTab === 'free' && !{{ $u['busy'] ? 'true' : 'false' }}) || (floorTab === 'busy' && {{ $u['busy'] ? 'true' : 'false' }})"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 transform scale-95"
                         x-transition:enter-end="opacity-100 transform scale-100">
                        <div class="hrn-unit-top">
                            <span class="hrn-unit-name">{{ $u['name'] }}</span>
                            <span @class(['hrn-pill', 'is-busy' => $u['busy']])>
                                {{ $u['busy'] ? 'Busy' : 'Free' }}
                            </span>
                        </div>

                        <div class="hrn-unit-meta">
                            <span class="hrn-unit-kind">{{ $u['kind'] }}</span>
                            @if ($u['seats'])
                                <span class="hrn-unit-seats">· {{ $u['seats'] }}</span>
                            @endif
                            <span class="hrn-unit-rate">· ₹{{ number_format($u['rate']) }}/hr</span>
                        </div>

                        <div class="hrn-unit-state">
                            @if ($u['busy'])
                                <div class="hrn-state-busy">
                                    <span class="hrn-busy-guest">{{ $u['guest'] ?: 'In use' }}</span>
                                    @if ($u['busy_until'])
                                        <span class="hrn-busy-until">until {{ $u['busy_until'] }}</span>
                                    @endif
                                </div>
                            @elseif ($u['next_at'])
                                <span class="hrn-state-next">Free — booked at {{ $u['next_at'] }}</span>
                            @else
                                <span class="hrn-state-clear">Available all day</span>
                            @endif
                        </div>

                        @if (! $u['busy'])
                            <button type="button" wire:click="openSeat({{ $u['id'] }})" class="hrn-seat-btn">
                                <x-filament::icon icon="heroicon-m-user-plus" />
                                <span>Seat a walk-in</span>
                            </button>
                        @endif
                    </div>
                @empty
                    <div class="hrn-desk-empty">
                        <x-filament::icon icon="heroicon-o-square-3-stack-3d" />
                        <p>No {{ $nounPlural }} set up at {{ $branch->branchName() }} yet.</p>
                        <p class="sub">Add them under Venue Management to operate the floor from here.</p>
                    </div>
                @endforelse
            </div>

            {{-- Expected Today / Arrivals Ledger --}}
            @if ($this->upcoming())
                <div class="hrn-desk-next">
                    <div class="hrn-desk-next-header">
                        <div class="hrn-next-title">
                            <x-filament::icon icon="heroicon-m-clock" />
                            <h3>Expected Today</h3>
                            <span class="hrn-count-badge">{{ count($this->upcoming()) }} arrivals</span>
                        </div>
                        <div class="hrn-search-box">
                            <x-filament::icon icon="heroicon-m-magnifying-glass" />
                            <input type="text" x-model="searchArrivals" placeholder="Quick find reservation..." />
                        </div>
                    </div>

                    <ul class="hrn-next-list">
                        @foreach ($this->upcoming() as $b)
                            @php
                                $searchText = strtolower(($b['who'] ?? '') . ' ' . ($b['where'] ?? '') . ' ' . ($b['at'] ?? ''));
                            @endphp
                            <li @class(['is-in' => $b['arrived']])
                                x-show="searchArrivals === '' || '{{ $searchText }}'.includes(searchArrivals.toLowerCase().trim())">
                                <span class="hrn-next-at">{{ $b['at'] }}</span>
                                <span class="hrn-next-who">
                                    <strong>{{ $b['who'] }}</strong>
                                    @if ($b['where']) <span class="hrn-next-where">· {{ $b['where'] }}</span> @endif
                                </span>
                                <span class="hrn-next-amt">
                                    ₹{{ number_format($b['amount']) }}
                                    @unless ($b['paid'])
                                        <span class="hrn-next-due">due</span>
                                    @else
                                        <span class="hrn-next-paid">paid</span>
                                    @endunless
                                </span>
                                @if ($b['arrived'])
                                    <span class="hrn-next-in">
                                        <x-filament::icon icon="heroicon-m-check" />
                                        Arrived
                                    </span>
                                @elseif ($this->canCheckIn())
                                    <button type="button" class="hrn-in-btn" wire:click="checkIn({{ $b['id'] }})">
                                        Mark Arrived
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Book Ahead Modal Sheet --}}
        @if ($this->reserving)
            @php $free = $this->freeUnitsAt(); @endphp
            <div class="hrn-sheet-scrim" wire:click="closeReserve"></div>
            <div x-data class="hrn-sheet" role="dialog" aria-modal="true" aria-label="Book ahead" @keydown.escape.window="$wire.closeReserve()">
                <div class="hrn-sheet-head">
                    <div>
                        <h3>Book Ahead</h3>
                        <p class="hrn-sheet-sub">Reservation for later today. Checks real-time floor availability.</p>
                    </div>
                    <button type="button" class="hrn-sheet-close" wire:click="closeReserve" title="Close">✕</button>
                </div>

                <div class="hrn-sheet-row">
                    <div>
                        <label>Start Time</label>
                        <input type="text" wire:model.live="reserveAt" placeholder="7:30 PM">
                    </div>
                    <div>
                        <label>Duration (Hours)</label>
                        <input type="number" min="1" max="12" wire:model.live="hours">
                    </div>
                </div>

                <label>Party Size <span>optional</span></label>
                <input type="number" min="1" wire:model.live="partySize" placeholder="Number of guests">

                {{-- Live availability readout --}}
                <div class="hrn-avail">
                    @if ($free)
                        <span class="hrn-avail-ok">✓ {{ count($free) }} free</span>
                        <select wire:model="reserveCourtId" aria-label="Which {{ $noun }}">
                            <option value="">Best fit — {{ $free[0]['label'] }}</option>
                            @foreach ($free as $u)
                                <option value="{{ $u['id'] }}">{{ $u['label'] }}</option>
                            @endforeach
                        </select>
                    @else
                        <span class="hrn-avail-no">✕ Nothing available for this window</span>
                    @endif
                </div>

                <label>Guest Name <span>optional</span></label>
                <input type="text" wire:model="guestName" placeholder="e.g. Anand Kumar">

                <label>Phone Number <span>optional</span></label>
                <input type="tel" wire:model="guestPhone" placeholder="10-digit mobile number">

                <div class="hrn-sheet-actions">
                    <button type="button" class="hrn-btn-ghost" wire:click="closeReserve">Cancel</button>
                    <button type="button" class="hrn-btn-go" wire:click="reserve"
                            wire:loading.attr="disabled" @disabled(! $free)>
                        <span wire:loading.remove wire:target="reserve">Confirm Reservation</span>
                        <span wire:loading wire:target="reserve" class="inline-flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Confirming...
                        </span>
                    </button>
                </div>
            </div>
        @endif

        {{-- Walk-in Seating Modal Sheet --}}
        @if ($this->seatingCourtId)
            <div class="hrn-sheet-scrim" wire:click="closeSeat"></div>
            <div x-data class="hrn-sheet" role="dialog" aria-modal="true" aria-label="Seat a walk-in" @keydown.escape.window="$wire.closeSeat()">
                <div class="hrn-sheet-head">
                    <div>
                        <h3>Seat a Walk-in</h3>
                        <p class="hrn-sheet-sub">Starts immediately. Instantly locks the {{ $noun }} on the floor.</p>
                    </div>
                    <button type="button" class="hrn-sheet-close" wire:click="closeSeat" title="Close">✕</button>
                </div>

                <label>Guest Name <span>optional</span></label>
                <input type="text" wire:model="guestName" placeholder="e.g. Walk-in Guest" autofocus>

                <label>Phone Number <span>optional</span></label>
                <input type="tel" wire:model="guestPhone" placeholder="10-digit mobile number">

                <div class="hrn-sheet-row">
                    <div>
                        <label>Party Size</label>
                        <input type="number" min="1" wire:model="partySize" placeholder="Headcount">
                    </div>
                    <div>
                        <label>Hours</label>
                        <input type="number" min="1" max="12" wire:model="hours">
                    </div>
                </div>

                <div class="hrn-sheet-actions">
                    <button type="button" class="hrn-btn-ghost" wire:click="closeSeat">Cancel</button>
                    <button type="button" class="hrn-btn-go" wire:click="seat" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="seat">Seat Immediately</span>
                        <span wire:loading wire:target="seat" class="inline-flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Seating...
                        </span>
                    </button>
                </div>
            </div>
        @endif
    @endif

    <style>
        .hrn-desk-workspace{display:flex;flex-direction:column;gap:16px;}

        /* Branch Selector */
        .hrn-desk-pick{background:var(--hrn-surface, #fff);border-radius:22px;padding:36px 24px;text-align:center;
            box-shadow:0 1px 3px rgba(15,23,42,.05),0 0 0 1px var(--hrn-border, #e2e8f0);max-width:640px;margin:30px auto;}
        .hrn-desk-pick-ic{width:56px;height:56px;border-radius:18px;background:rgba(37,99,235,.1);color:#2563eb;
            display:flex;align-items:center;justify-content:center;margin:0 auto 16px;}
        .hrn-desk-pick-ic svg{width:28px;height:28px;}
        .hrn-desk-pick h2{font-size:19px;font-weight:800;color:var(--hrn-ink, #0f172a);margin:0 0 6px;letter-spacing:-.02em;}
        .hrn-desk-pick p{font-size:13.5px;color:var(--hrn-ink-3, #64748b);margin:0 0 22px;line-height:1.5;}
        .hrn-desk-pick-row{display:flex;flex-wrap:wrap;gap:12px;justify-content:center;}
        .hrn-desk-pick-btn{display:flex;flex-direction:column;gap:3px;padding:14px 22px;border:0;
            border-radius:14px;background:var(--hrn-app-bg, #f8fafc);box-shadow:inset 0 0 0 1px var(--hrn-border, #e2e8f0);
            cursor:pointer;transition:all .15s ease;text-align:center;}
        .hrn-desk-pick-btn:hover{background:color-mix(in srgb,var(--hrn-surface) 90%,#2563eb);box-shadow:inset 0 0 0 1.5px #2563eb;transform:translateY(-1px);}
        .hrn-desk-pick-name{font-size:14.5px;font-weight:750;color:var(--hrn-ink, #1e3a6b);}
        .hrn-desk-pick-sub{font-size:11.5px;color:var(--hrn-ink-3, #64748b);}

        /* Command Strip */
        .hrn-desk-strip{display:flex;align-items:center;gap:16px;flex-wrap:wrap;background:var(--hrn-surface, #fff);
            border-radius:20px;padding:14px 20px;box-shadow:0 1px 3px rgba(15,23,42,.04),0 0 0 1px var(--hrn-border, #e2e8f0);}
        .hrn-desk-stats-group{display:flex;align-items:center;gap:18px;}
        .hrn-desk-stat{display:flex;flex-direction:column;cursor:pointer;padding:4px 8px;border-radius:10px;transition:background .15s;}
        .hrn-desk-stat:hover{background:rgba(0,0,0,.03);}
        .hrn-desk-stat.is-active{background:rgba(37,99,235,.1);}
        .hrn-desk-val{font-size:24px;font-weight:850;color:var(--hrn-ink, #0f172a);line-height:1;
            font-variant-numeric:tabular-nums;letter-spacing:-.02em;}
        .hrn-desk-val.hrn-free{color:#10b981;}
        .hrn-desk-lbl{font-size:11px;color:var(--hrn-ink-3, #64748b);text-transform:uppercase;letter-spacing:.05em;font-weight:700;margin-top:4px;}

        /* Filter Tabs */
        .hrn-filter-tabs{display:flex;align-items:center;gap:6px;background:var(--hrn-app-bg, #f1f5f9);
            padding:4px;border-radius:12px;}
        .hrn-tab-pill{padding:6px 12px;border:0;border-radius:9px;background:none;cursor:pointer;
            font-size:12px;font-weight:700;color:var(--hrn-ink-3, #64748b);transition:all .15s ease;}
        .hrn-tab-pill:hover{color:var(--hrn-ink, #0f172a);}
        .hrn-tab-pill.active{background:var(--hrn-surface, #fff);color:var(--hrn-ink, #0f172a);
            box-shadow:0 1px 3px rgba(0,0,0,.08);}
        .hrn-tab-pill.pill-free.active{color:#059669;}
        .hrn-tab-pill.pill-busy.active{color:#d97706;}

        /* Actions & Branch Badge */
        .hrn-desk-actions{margin-left:auto;display:flex;align-items:center;gap:10px;}
        .hrn-desk-branch{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:700;
            color:#2563eb;background:rgba(37,99,235,.08);border-radius:999px;padding:6px 14px;}
        .hrn-desk-branch svg{width:14px;height:14px;}
        .hrn-ahead-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border:0;
            border-radius:12px;cursor:pointer;background:linear-gradient(135deg,#2563eb,#3b82f6);
            color:#fff;font-size:13px;font-weight:700;box-shadow:0 4px 12px rgba(37,99,235,.25);
            transition:filter .15s,transform .1s;}
        .hrn-ahead-btn:hover{filter:brightness(1.08);}
        .hrn-ahead-btn:active{transform:scale(.98);}
        .hrn-ahead-btn svg{width:16px;height:16px;}

        /* Floor Units Grid */
        .hrn-desk-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(min(100%,230px),1fr));}
        .hrn-unit{background:var(--hrn-surface, #fff);border-radius:18px;padding:16px;
            box-shadow:0 1px 3px rgba(15,23,42,.04),0 0 0 1px var(--hrn-border, #e2e8f0);
            display:flex;flex-direction:column;gap:8px;position:relative;transition:border-color .15s,box-shadow .15s;}
        .hrn-unit:hover{box-shadow:0 8px 20px -8px rgba(0,0,0,.1);}
        .hrn-unit.is-busy{background:color-mix(in srgb,var(--hrn-surface) 97%,#000);}
        .hrn-unit-top{display:flex;align-items:center;justify-content:space-between;gap:8px;}
        .hrn-unit-name{font-size:15px;font-weight:750;color:var(--hrn-ink, #0f172a);}
        .hrn-pill{font-size:10.5px;font-weight:750;text-transform:uppercase;letter-spacing:.05em;
            padding:3px 9px;border-radius:999px;background:rgba(16,185,129,.12);color:#059669;}
        .hrn-pill.is-busy{background:rgba(100,116,139,.15);color:#64748b;}
        .hrn-unit-meta{font-size:12px;color:var(--hrn-ink-3, #64748b);font-weight:550;}
        .hrn-unit-state{font-size:12.5px;color:var(--hrn-ink-2, #334155);min-height:22px;line-height:1.4;}
        .hrn-busy-guest{font-weight:700;color:var(--hrn-ink, #0f172a);}
        .hrn-busy-until{color:var(--hrn-ink-3, #64748b);font-size:11.5px;}
        .hrn-state-next{color:#d97706;font-weight:600;}
        .hrn-state-clear{color:#10b981;font-weight:650;}

        .hrn-seat-btn{margin-top:4px;height:42px;display:inline-flex;align-items:center;justify-content:center;
            gap:7px;border:0;border-radius:12px;cursor:pointer;background:#2563eb;color:#fff;
            font-size:13px;font-weight:700;transition:filter .15s,transform .1s;}
        .hrn-seat-btn:hover{filter:brightness(1.08);}
        .hrn-seat-btn:active{transform:scale(.98);}
        .hrn-seat-btn svg{width:16px;height:16px;}

        .hrn-desk-empty{grid-column:1/-1;background:var(--hrn-surface, #fff);border-radius:18px;padding:32px 20px;
            text-align:center;color:var(--hrn-ink-3, #64748b);box-shadow:0 0 0 1px var(--hrn-border, #e2e8f0);
            display:flex;flex-direction:column;align-items:center;gap:6px;}
        .hrn-desk-empty svg{width:40px;height:40px;opacity:.5;margin-bottom:6px;}
        .hrn-desk-empty p{font-size:14px;font-weight:650;margin:0;}
        .hrn-desk-empty .sub{font-size:12px;font-weight:400;}

        /* Expected Today / Arrivals Ledger */
        .hrn-desk-next{margin-top:10px;background:var(--hrn-surface, #fff);border-radius:20px;padding:18px 22px;
            box-shadow:0 1px 3px rgba(15,23,42,.04),0 0 0 1px var(--hrn-border, #e2e8f0);}
        .hrn-desk-next-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px;}
        .hrn-next-title{display:flex;align-items:center;gap:8px;}
        .hrn-next-title svg{width:18px;height:18px;color:#2563eb;}
        .hrn-next-title h3{font-size:14px;font-weight:750;color:var(--hrn-ink, #0f172a);margin:0;letter-spacing:-.01em;}
        .hrn-count-badge{font-size:11px;font-weight:700;color:var(--hrn-ink-3, #64748b);
            background:var(--hrn-app-bg, #f1f5f9);padding:2px 8px;border-radius:999px;}
        
        .hrn-search-box{display:flex;align-items:center;gap:8px;position:relative;}
        .hrn-search-box svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);
            width:14px;height:14px;color:var(--hrn-ink-3);pointer-events:none;}
        .hrn-search-box input{height:34px;padding:0 12px 0 32px;border-radius:10px;border:1px solid var(--hrn-border, #cbd5e1);
            background:var(--hrn-app-bg, #f8fafc);font-size:12px;color:var(--hrn-ink);outline:none;width:220px;}
        .hrn-search-box input:focus{border-color:#2563eb;}

        .hrn-next-list{list-style:none;margin:0;padding:0;}
        .hrn-next-list li{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--hrn-border, #f1f5f9);font-size:13.5px;}
        .hrn-next-list li:last-child{border-bottom:0;}
        .hrn-next-at{font-weight:750;color:var(--hrn-ink, #0f172a);min-width:76px;font-variant-numeric:tabular-nums;font-size:13px;}
        .hrn-next-who{flex:1;color:var(--hrn-ink-2, #334155);min-width:0;}
        .hrn-next-who strong{color:var(--hrn-ink, #0f172a);}
        .hrn-next-where{color:var(--hrn-ink-3, #64748b);font-size:12px;}
        .hrn-next-amt{font-weight:750;color:var(--hrn-ink, #0f172a);font-variant-numeric:tabular-nums;}
        .hrn-next-due{font-size:10.5px;font-weight:700;color:#dc2626;background:rgba(239,68,68,.1);padding:2px 6px;border-radius:6px;margin-left:4px;}
        .hrn-next-paid{font-size:10.5px;font-weight:700;color:#059669;background:rgba(16,185,129,.1);padding:2px 6px;border-radius:6px;margin-left:4px;}

        .hrn-in-btn{padding:6px 14px;border:0;border-radius:10px;cursor:pointer;
            background:#10b981;color:#fff;font-size:12px;font-weight:700;transition:filter .15s;}
        .hrn-in-btn:hover{filter:brightness(1.08);}
        .hrn-next-in{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;
            color:#059669;background:rgba(16,185,129,.12);border-radius:999px;padding:4px 10px;}
        .hrn-next-in svg{width:13px;height:13px;}

        /* Modal Dialog Sheets */
        .hrn-sheet-scrim{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:60;backdrop-filter:blur(3px);}
        .hrn-sheet{position:fixed;z-index:61;left:50%;top:50%;transform:translate(-50%,-50%);
            width:min(94vw,420px);background:var(--hrn-surface, #fff);border-radius:22px;padding:24px;
            box-shadow:0 24px 60px rgba(15,23,42,.28);border:1px solid var(--hrn-border, #e2e8f0);}
        .hrn-sheet-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;}
        .hrn-sheet-head h3{font-size:17px;font-weight:800;color:var(--hrn-ink, #0f172a);margin:0;letter-spacing:-.01em;}
        .hrn-sheet-head .hrn-sheet-sub{font-size:12.5px;color:var(--hrn-ink-3, #64748b);margin:3px 0 0;}
        .hrn-sheet-close{background:none;border:0;font-size:16px;color:var(--hrn-ink-3);cursor:pointer;padding:4px 8px;border-radius:6px;}
        .hrn-sheet label{display:block;font-size:12px;font-weight:650;color:var(--hrn-ink-2, #334155);margin:12px 0 5px;}
        .hrn-sheet label span{font-weight:450;color:var(--hrn-ink-3, #94a3b8);font-size:11px;}
        .hrn-sheet input{width:100%;height:44px;padding:0 14px;border-radius:12px;border:1px solid var(--hrn-border, #cbd5e1);
            font-size:14px;background:var(--hrn-app-bg, #f8fafc);color:var(--hrn-ink, #0f172a);outline:none;transition:border-color .15s;}
        .hrn-sheet input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.18);}
        .hrn-sheet-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
        .hrn-sheet-actions{display:flex;gap:10px;margin-top:20px;}
        .hrn-btn-ghost,.hrn-btn-go{flex:1;height:44px;border:0;border-radius:12px;cursor:pointer;font-size:13.5px;font-weight:700;}
        .hrn-btn-ghost{background:var(--hrn-app-bg, #f1f5f9);color:var(--hrn-ink-2, #334155);}
        .hrn-btn-go{background:#2563eb;color:#fff;}
        .hrn-btn-go:hover{filter:brightness(1.08);}
        .hrn-btn-go:disabled{opacity:.55;cursor:not-allowed;}

        .hrn-avail{display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap;}
        .hrn-avail select{flex:1;min-width:140px;height:40px;padding:0 12px;border-radius:10px;
            border:1px solid var(--hrn-border, #cbd5e1);font-size:13px;background:var(--hrn-app-bg, #f8fafc);color:var(--hrn-ink);}
        .hrn-avail-ok{font-size:11.5px;font-weight:700;color:#059669;background:rgba(16,185,129,.12);
            border-radius:999px;padding:4px 10px;white-space:nowrap;}
        .hrn-avail-no{font-size:12px;font-weight:650;color:#d97706;}

        /* Dark Mode */
        .dark .hrn-desk-pick,
        .dark .hrn-desk-strip,
        .dark .hrn-unit,
        .dark .hrn-desk-next,
        .dark .hrn-sheet,
        .dark .hrn-desk-empty{
            background:#111827;box-shadow:0 0 0 1px #1f2937;
        }
        .dark .hrn-unit.is-busy{background:#0b1120;}
        .dark .hrn-desk-pick-btn{background:#1f2937;box-shadow:inset 0 0 0 1px #374151;}
        .dark .hrn-filter-tabs{background:#1f2937;}
        .dark .hrn-tab-pill.active{background:#111827;color:#f8fafc;}
        .dark .hrn-search-box input{background:#1f2937;border-color:#374151;color:#f8fafc;}
        .dark .hrn-sheet input,
        .dark .hrn-avail select{background:#1f2937;border-color:#374151;color:#f8fafc;}
        .dark .hrn-btn-ghost{background:#1f2937;color:#cbd5e1;}
        .dark .hrn-desk-next-header{border-bottom-color:#1f2937;}
        .dark .hrn-next-list li{border-bottom-color:#1f2937;}

        @media (prefers-reduced-motion: reduce) {
            .hrn-tab-pill, .hrn-btn-go, .hrn-btn-ghost, .hrn-desk-pick-btn, .hrn-desk-stat {
                transition: none !important;
                transform: none !important;
            }
        }
    </style>
</x-filament-panels::page>
