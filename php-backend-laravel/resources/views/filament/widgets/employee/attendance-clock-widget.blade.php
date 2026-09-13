<x-filament-widgets::widget>
    @php
        $employee = $this->employee;
        $att = $this->todayAttendance;
        $isClockedIn = $att !== null && $att->isCurrentlyClockedIn();
        $isClockedOut = $att !== null && $att->clock_out_at !== null;
        $isOnBreak = $att !== null && $att->isCurrentlyOnBreak();
        $venue = $employee?->venue;
    @endphp

    <div id="attendance-hero"
         class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 sm:p-8 transition-all"
         x-data="{
             currentTime: '',
             isLocating: false,
             locError: null,
             timer: null,
             init() {
                 this.updateClock();
                 this.timer = setInterval(() => this.updateClock(), 1000);
                 this.detectLocation();
             },
             updateClock() {
                 const now = new Date();
                 this.currentTime = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
             },
             detectLocation() {
                 if ('geolocation' in navigator) {
                     this.isLocating = true;
                     navigator.geolocation.getCurrentPosition(
                         (pos) => {
                             this.isLocating = false;
                             $wire.updateLocation(pos.coords.latitude, pos.coords.longitude);
                         },
                         (err) => {
                             this.isLocating = false;
                             this.locError = err.message;
                         },
                         { enableHighAccuracy: true, timeout: 10000, maximumAge: 15000 }
                     );
                 }
             }
         }">

        {{-- Top Section: Hero Eyebrow & Status Badge --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-100 shrink-0">
                    <x-heroicon-o-clock class="w-5 h-5" />
                </span>
                <div>
                    <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">Live Attendance Command</h2>
                    <p class="text-xs text-slate-500 font-medium">{{ now()->format('l, d F Y') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3 sm:justify-end">
                @if($isOnBreak)
                    <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                        <span class="w-2 h-2 rounded-full bg-amber-500 motion-reduce:animate-none animate-ping"></span>
                        <span>On Break</span>
                    </span>
                @elseif($isClockedIn)
                    <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 motion-reduce:animate-none animate-pulse"></span>
                        <span>Active on Duty</span>
                    </span>
                @elseif($isClockedOut)
                    <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                        <svg class="w-3.5 h-3.5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span>Shift Concluded</span>
                    </span>
                @else
                    <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        <span>Ready to Clock In</span>
                    </span>
                @endif
            </div>
        </div>

        {{-- Clock Hero Center --}}
        <div class="py-4 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-2">
            <div>
                <span class="font-mono tabular-nums text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-slate-900" x-text="currentTime">--:--:--</span>
                <div class="text-xs font-medium text-slate-400 mt-1">Indian Standard Time (IST) &bull; High Accuracy GNSS Sync</div>
            </div>

            @if($venue)
                <div class="text-left sm:text-right text-xs text-slate-500 font-medium">
                    <span class="text-slate-400 block text-[11px] uppercase tracking-wider font-semibold">Duty Hub</span>
                    <span class="font-bold text-slate-800 text-sm">{{ $venue->name }}</span>
                </div>
            @endif
        </div>

        {{-- Spacious Telemetry Strip (No nested card boxes) --}}
        <div class="border-y border-slate-100 py-4 my-4 grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
            <div>
                <span class="text-[11px] font-bold text-slate-400 block uppercase tracking-wider">Clock-In Time</span>
                <span class="text-base sm:text-lg font-extrabold text-slate-900 mt-0.5 block font-mono">
                    {{ $att?->clock_in_at ? \Illuminate\Support\Carbon::parse($att->clock_in_at)->format('h:i A') : '—' }}
                </span>
            </div>

            <div>
                <span class="text-[11px] font-bold text-slate-400 block uppercase tracking-wider">Live Duration</span>
                <span class="text-base sm:text-lg font-extrabold text-emerald-700 mt-0.5 block font-mono">
                    @if($isClockedOut)
                        {{ $att->formatted_work_duration ?? 'Complete' }}
                    @elseif($isClockedIn)
                        {{ $att?->formatted_work_duration ?? 'Calculating...' }}
                    @else
                        0h 0m
                    @endif
                </span>
            </div>

            <div>
                <span class="text-[11px] font-bold text-slate-400 block uppercase tracking-wider">Geofence Status</span>
                <span class="text-sm sm:text-base font-bold mt-0.5 block truncate">
                    @if($geofenceStatus === 'inside')
                        <span class="text-emerald-700 font-extrabold">✓ In-Zone Verified</span>
                    @elseif($geofenceStatus === 'outside')
                        <span class="text-amber-700 font-bold">Outside Radius</span>
                    @else
                        <span class="text-slate-600 font-medium">Radar Checking</span>
                    @endif
                </span>
            </div>

            <div>
                <span class="text-[11px] font-bold text-slate-400 block uppercase tracking-wider">Duty Station</span>
                <span class="text-sm sm:text-base font-bold text-slate-800 mt-0.5 block truncate" title="{{ $venue ? $venue->name : 'Headquarters' }}">
                    {{ $venue ? $venue->name : 'HARAAN Arena' }}
                </span>
            </div>
        </div>

        {{-- Geofence Radar Status Line --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs text-slate-600 mb-5">
            <div class="flex items-center gap-2">
                <x-heroicon-o-map-pin class="w-4 h-4 text-slate-400 shrink-0" />
                <span>
                    <template x-if="isLocating">
                        <span class="text-emerald-600 font-semibold motion-reduce:animate-none animate-pulse">Acquiring GPS fix...</span>
                    </template>
                    <template x-if="!isLocating">
                        <span>
                            @if($distanceMeters !== null)
                                Proximity: <strong class="text-slate-900 font-mono">{{ number_format($distanceMeters, 0) }}m</strong> away
                                (Radius: &le; {{ $employee?->geofence_radius_meters ?? 200 }}m)
                            @else
                                Radar ready. Location coordinates standby.
                            @endif
                        </span>
                    </template>
                </span>
            </div>

            <div class="flex items-center gap-2">
                @if($geofenceStatus === 'inside')
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        <svg class="w-3.5 h-3.5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Geofence Verified
                    </span>
                @elseif($geofenceStatus === 'outside')
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                        Outside Geofence
                    </span>
                @endif
            </div>
        </div>

        {{-- Geofence Warning Helper if outside or error --}}
        <div x-show="locError" class="mb-4 p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800 flex items-center justify-between gap-2" style="display: none;">
            <span>Location access blocked or timed out. Please enable browser GPS or use the front-desk QR token.</span>
            <button type="button" @click="detectLocation()" class="underline font-bold whitespace-nowrap text-amber-900 hover:text-amber-950">Retry GPS</button>
        </div>

        {{-- Action Buttons Row (Touch targets >= 52px) --}}
        <div class="flex flex-wrap items-center gap-3 pt-1">
            @if(!$isClockedIn && !$isClockedOut)
                <button wire:click="clockIn"
                        @click="if(window.hrnHaptic) window.hrnHaptic('success')"
                        wire:loading.attr="disabled"
                        class="flex-1 min-w-[160px] min-h-[52px] flex items-center justify-center gap-2 py-3 px-6 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-500 shadow-sm shadow-emerald-600/20 transition-all active:scale-[0.99] cursor-pointer">
                    <x-heroicon-m-arrow-right-on-rectangle class="w-4 h-4" />
                    <span wire:loading.remove wire:target="clockIn">Clock In (GPS)</span>
                    <span wire:loading wire:target="clockIn" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        Recording Punch...
                    </span>
                </button>
            @elseif($isClockedIn)
                <button wire:click="toggleBreak"
                        @click="if(window.hrnHaptic) window.hrnHaptic('tap')"
                        wire:loading.attr="disabled"
                        class="flex-1 min-w-[140px] min-h-[52px] flex items-center justify-center gap-2 py-3 px-5 rounded-xl text-sm font-bold border border-amber-200 text-amber-900 bg-amber-50 hover:bg-amber-100 transition-all active:scale-[0.99] cursor-pointer">
                    <x-heroicon-m-pause class="w-4 h-4" style="width: 16px; height: 16px; flex-shrink: 0;" />
                    <span>{{ $isOnBreak ? 'Conclude Break' : 'Start Lunch / Break' }}</span>
                </button>

                <button wire:click="clockOut"
                        @click="if(window.hrnHaptic) window.hrnHaptic('success')"
                        wire:loading.attr="disabled"
                        wire:confirm="Are you sure you wish to clock out and conclude your shift for today?"
                        class="flex-1 min-w-[140px] min-h-[52px] flex items-center justify-center gap-2 py-3 px-5 rounded-xl text-sm font-bold text-white bg-rose-600 hover:bg-rose-500 shadow-sm shadow-rose-600/20 transition-all active:scale-[0.99] cursor-pointer">
                    <x-heroicon-m-arrow-left-on-rectangle class="w-4 h-4" style="width: 16px; height: 16px; flex-shrink: 0;" />
                    <span wire:loading.remove wire:target="clockOut">Clock Out</span>
                    <span wire:loading wire:target="clockOut" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        Concluding Shift...
                    </span>
                </button>
            @else
                <div class="flex-1 min-h-[52px] p-3 text-center text-xs font-bold text-emerald-800 bg-emerald-50 rounded-xl border border-emerald-200 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    <span>Shift concluded for today (Duration: {{ $att->formatted_work_duration ?? 'Complete' }}).</span>
                </div>
            @endif

            {{-- Dynamic QR Token Modal Trigger --}}
            <button wire:click="openQrModal"
                    type="button"
                    class="min-h-[52px] min-w-[52px] p-3 rounded-xl border border-slate-200/80 bg-slate-50 text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-all cursor-pointer flex items-center justify-center active:scale-[0.99]"
                    title="Show Dynamic Attendance QR">
                <x-heroicon-o-qr-code class="w-5 h-5" />
            </button>
        </div>

        {{-- Dynamic QR Modal --}}
        @if($showQrModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div class="w-full max-w-sm rounded-2xl bg-white border border-slate-200 p-6 shadow-2xl"
                     x-data="{ timeLeft: 60, timer: null }"
                     x-init="timer = setInterval(() => { if (timeLeft > 0) timeLeft--; else $wire.openQrModal(); }, 1000)">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                                <x-heroicon-o-qr-code class="w-4 h-4" />
                            </span>
                            <h4 class="text-sm font-bold text-slate-900">Dynamic Punch QR</h4>
                        </div>
                        <button wire:click="closeQrModal" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="py-6 flex flex-col items-center text-center">
                        <div class="p-4 bg-white rounded-xl shadow-inner border border-slate-200 inline-block mb-3">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($qrToken) }}"
                                 alt="Attendance QR Code"
                                 class="w-40 h-40 block" />
                        </div>
                        <div class="text-sm font-bold text-slate-900">{{ $employee?->full_name }}</div>
                        <div class="text-xs text-slate-500 mb-3">Code: {{ $employee?->employee_code }}</div>
                        <div class="inline-flex items-center gap-1.5 text-xs font-mono text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full font-bold">
                            <span>Refreshes in:</span>
                            <span x-text="timeLeft + 's'"></span>
                        </div>
                    </div>

                    <button wire:click="closeQrModal"
                            class="w-full py-2.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-all cursor-pointer">
                        Close
                    </button>
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
