<x-filament-widgets::widget>
    @php
        $todayRoster = $this->todayRoster;
        $upcoming = $this->upcomingRosters;
        $shift = $todayRoster?->shift;
    @endphp

    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 transition-all flex flex-col justify-between h-full">
        <div>
            {{-- Header --}}
            <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 shrink-0">
                        <x-heroicon-o-calendar-days class="w-5 h-5" />
                    </span>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-slate-900 tracking-tight">Shift &amp; Roster</h3>
                        <p class="text-[11px] text-slate-400 font-medium">Daily assigned shift</p>
                    </div>
                </div>

                @if($todayRoster)
                    <button wire:click="openSwapModal"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-all cursor-pointer active:scale-[0.99]">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                        <span>Request Swap</span>
                    </button>
                @endif
            </div>

            {{-- Today's Shift Card --}}
            @if($shift)
                <div class="p-4 rounded-xl bg-slate-50/80 border border-slate-200/60 mb-4 transition-all">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Today's Shift</span>
                        <span class="text-[11px] px-2.5 py-0.5 rounded-md bg-slate-900 text-white font-mono font-bold tracking-wide">
                            {{ $shift->code }}
                        </span>
                    </div>
                    <div class="text-base font-black text-slate-900 mb-2">
                        {{ $shift->name }}
                    </div>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex items-center gap-2 text-slate-800 font-semibold font-mono">
                            <x-heroicon-o-clock class="w-4 h-4 text-slate-500 shrink-0" />
                            <span>{{ $shift->formatted_timing }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-slate-500 font-medium">
                            <x-heroicon-o-building-office class="w-4 h-4 text-slate-400 shrink-0" />
                            <span class="truncate">{{ $todayRoster->venue?->name ?? 'Assigned Branch' }}</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="p-4 text-center rounded-xl bg-slate-50 border border-slate-100 text-xs text-slate-500 font-medium mb-4">
                    ☕ No scheduled shift for today. Scheduled rest day.
                </div>
            @endif

            {{-- Upcoming Shifts List --}}
            <div class="space-y-1">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Upcoming Days</div>
                @forelse($upcoming as $item)
                    <div class="flex items-center justify-between py-2 px-1 border-b border-slate-100 last:border-b-0 text-xs">
                        <div class="flex items-center gap-2.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                            <div>
                                <span class="font-bold text-slate-800">{{ \Illuminate\Support\Carbon::parse($item->roster_date)->format('D, M d') }}</span>
                                <span class="text-[11px] text-slate-400 block">{{ $item->shift?->name ?? 'Shift' }}</span>
                            </div>
                        </div>
                        <span class="text-[11px] font-mono font-bold text-slate-700 bg-slate-50 px-2 py-0.5 rounded border border-slate-200/80">
                            {{ $item->shift?->formatted_timing }}
                        </span>
                    </div>
                @empty
                    <div class="text-xs text-slate-400 italic py-2">No further shifts scheduled for this week.</div>
                @endforelse
            </div>
        </div>

        {{-- Footer link --}}
        <div class="pt-3 border-t border-slate-100 mt-4 flex items-center justify-between">
            <span class="text-[11px] text-slate-400">Weekly Schedule</span>
            <a href="{{ route('filament.employee.pages.my-attendance-page') }}" class="text-xs font-bold text-slate-700 hover:text-slate-900 hover:underline">
                View Full Roster &rarr;
            </a>
        </div>

        {{-- Shift Swap Modal --}}
        @if($showSwapModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div class="w-full max-w-md rounded-2xl bg-white border border-slate-200 p-6 shadow-2xl">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                            </span>
                            <h4 class="text-sm font-bold text-slate-900">Request Shift Swap</h4>
                        </div>
                        <button wire:click="closeSwapModal" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <form wire:submit="submitSwapRequest" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Select Colleague</label>
                            <select wire:model="selectedColleagueId"
                                    class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 text-slate-800 p-3 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all"
                                    required>
                                <option value="">-- Choose Colleague to Exchange Shift With --</option>
                                @foreach($this->colleagues as $c)
                                    <option value="{{ $c->id }}">{{ $c->full_name }} ({{ $c->employee_code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Reason for Swap</label>
                            <textarea wire:model="swapReason"
                                      rows="3"
                                      class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 text-slate-800 p-3 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all"
                                      placeholder="Explain why you need this shift exchange..."
                                      required></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" wire:click="closeSwapModal" class="px-4 py-2 text-xs font-bold text-slate-600 rounded-xl hover:bg-slate-100 transition-all cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    class="px-5 py-2.5 text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 rounded-xl transition-all shadow-sm active:scale-[0.99] cursor-pointer">
                                <span wire:loading.remove wire:target="submitSwapRequest">Submit Request</span>
                                <span wire:loading wire:target="submitSwapRequest" class="inline-flex items-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    Routing to Supervisor...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
