<x-filament-panels::page>
    {{-- Scoped styling only — reuses the panel-wide --hrn-* design tokens
         (resources/views/filament/theme.blade.php). No logic changes here. --}}
    <style>
        .vb-slot-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
        .vb-time{font-size:15px;font-weight:700;color:var(--hrn-ink);line-height:1.2;}
        .vb-price{font-size:12.5px;color:var(--hrn-ink-3);margin-top:2px;}
        .vb-occ{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;
            padding:4px 11px;border-radius:999px;white-space:nowrap;
            background:var(--_bg,var(--hrn-ok-bg));color:var(--_fg,var(--hrn-ok));}
        .vb-meter{height:5px;border-radius:999px;background:var(--hrn-track);overflow:hidden;margin-top:10px;}
        .vb-meter i{display:block;height:100%;border-radius:999px;background:var(--_fg,var(--hrn-ok-strong));transition:width .4s ease;}
        .vb-cust{display:flex;align-items:center;gap:11px;padding-top:12px;margin-top:12px;border-top:1px solid var(--hrn-border);}
        .vb-ini{width:32px;height:32px;border-radius:50%;flex:none;display:flex;align-items:center;
            justify-content:center;color:#fff;font-size:12.5px;font-weight:700;}
        .vb-cust-main{flex:1;min-width:0;}
        .vb-cust-name{font-size:13.5px;font-weight:600;color:var(--hrn-ink);}
        .vb-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:3px;}
        .vb-tag{font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;
            background:var(--hrn-idle-bg);color:var(--hrn-ink-2);}
        .vb-tag--in{background:var(--hrn-ok-bg);color:var(--hrn-ok);}
    </style>

    <div class="space-y-6">
        {{-- Venue + date controls --}}
        <div class="flex flex-wrap items-end gap-4">
            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700 dark:text-gray-200">Venue</span>
                <select wire:model.live="venueId"
                        class="block w-56 rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    @foreach ($this->venueOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700 dark:text-gray-200">Date</span>
                <input type="date" wire:model.live="date"
                       class="block rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </label>

            <x-filament::button wire:click="toggleClosed" :color="$this->isBlocked() ? 'success' : 'gray'">
                {{ $this->isBlocked() ? 'Reopen day' : 'Close day' }}
            </x-filament::button>
        </div>

        @if ($this->isBlocked())
            <x-filament::section>
                <p class="text-danger-600 font-medium">This day is closed (maintenance / holiday). Reopen it to take bookings.</p>
            </x-filament::section>
        @else
            {{-- Add walk-in booking --}}
            <x-filament::section>
                <x-slot name="heading">Add walk-in booking</x-slot>

                <div class="flex flex-wrap items-end gap-3">
                    <select wire:model="formSlotId"
                            class="block w-64 rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">Select slot…</option>
                        @foreach ($this->openSlotOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <input type="text" wire:model="guestName" placeholder="Customer name"
                           class="block rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <input type="text" wire:model="guestPhone" placeholder="Phone (optional)"
                           class="block rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">

                    <x-filament::button wire:click="addWalkIn">Book</x-filament::button>
                </div>
            </x-filament::section>
        @endif

        {{-- Slot grid --}}
        <div class="space-y-3">
            @forelse ($this->slots() as $slot)
                @php
                    $cap = max(0, (int) $slot['capacity']);
                    $booked = (int) $slot['booked'];
                    $pct = $cap > 0 ? min(100, (int) round($booked / $cap * 100)) : 0;
                    $st = $slot['available'] <= 0 ? 'down' : ($cap > 0 && $booked / $cap >= 0.85 ? 'warn' : 'ok');
                    $occStyle = "--_bg:var(--hrn-{$st}-bg);--_fg:var(--hrn-{$st})";
                @endphp
                <x-filament::section>
                    <div class="vb-slot-top">
                        <div>
                            <div class="vb-time">{{ $slot['time'] ?: $slot['label'] }}</div>
                            @if ($slot['price'] > 0)
                                <div class="vb-price">₹{{ number_format($slot['price']) }}</div>
                            @endif
                        </div>
                        <span class="vb-occ" style="{{ $occStyle }}">
                            {{ $booked }}/{{ $cap }} booked
                        </span>
                    </div>
                    @if ($cap > 0)
                        <div class="vb-meter" style="--_fg:var(--hrn-{{ $st }}-strong)"><i style="width:{{ $pct }}%"></i></div>
                    @endif

                    @foreach ($slot['bookings'] as $b)
                        @php
                            $name = trim((string) $b['customer']) ?: 'Guest';
                            $hue = crc32($name) % 360;
                            $ini = strtoupper(mb_substr($name, 0, 1));
                        @endphp
                        <div class="vb-cust">
                            <div class="vb-ini" style="background:hsl({{ $hue }} 52% 46%)">{{ $ini }}</div>
                            <div class="vb-cust-main">
                                <div class="vb-cust-name">{{ $b['customer'] }}</div>
                                <div class="vb-tags">
                                    <span class="vb-tag">{{ $b['channel'] === 'offline' ? 'Walk-in' : 'Online' }}</span>
                                    @if ($b['checked_in'] > 0)
                                        <span class="vb-tag vb-tag--in">Checked in</span>
                                    @endif
                                </div>
                            </div>
                            <x-filament::button size="xs" color="danger" wire:click="cancelBooking({{ $b['id'] }})">
                                Cancel
                            </x-filament::button>
                        </div>
                    @endforeach
                </x-filament::section>
            @empty
                <x-filament::section>
                    <p class="text-gray-500">No slots configured for this venue.</p>
                </x-filament::section>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
