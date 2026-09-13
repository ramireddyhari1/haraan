<x-filament-widgets::widget>
    @php
        $balances = $this->balances;
    @endphp

    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 transition-all flex flex-col justify-between h-full">
        <div>
            {{-- Header --}}
            <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 shrink-0">
                        <x-heroicon-o-briefcase class="w-5 h-5" />
                    </span>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-slate-900 tracking-tight">Leave Quotas</h3>
                        <p class="text-[11px] text-slate-400 font-medium">Annual balance snapshot</p>
                    </div>
                </div>

                <button wire:click="openApplyModal"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-all cursor-pointer active:scale-[0.99]">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    <span>Apply Leave</span>
                </button>
            </div>

            {{-- Balance Quota Rows (Clean flat layout) --}}
            <div class="space-y-3 mb-3">
                @forelse($balances as $bal)
                    <div class="py-1">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-bold text-slate-800">{{ $bal->leaveType?->code }} &bull; <span class="font-normal text-slate-500">{{ $bal->leaveType?->name }}</span></span>
                            <span class="text-slate-900 font-extrabold font-mono text-xs">{{ $bal->remaining_days }}d left</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                            @php
                                $alloc = max(1, (float)$bal->allocated_days);
                                $usedPct = min(100, ((float)$bal->used_days / $alloc) * 100);
                            @endphp
                            <div class="bg-emerald-600 h-1.5 rounded-full transition-all duration-500" style="width: {{ $usedPct }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-xs text-slate-400 italic py-4">
                        No leave quotas configured.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="pt-3 border-t border-slate-100 mt-2 flex items-center justify-between">
            <span class="text-[11px] text-slate-400">{{ now()->year }} Annual Cycle</span>
            <a href="{{ route('filament.employee.pages.my-leaves-page') }}" class="text-xs font-bold text-slate-700 hover:text-slate-900 hover:underline">
                Leave History &rarr;
            </a>
        </div>

        {{-- Apply Leave Modal --}}
        @if($showApplyModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div class="w-full max-w-md rounded-2xl bg-white border border-slate-200 p-6 shadow-2xl">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center">
                                <x-heroicon-o-briefcase class="w-4 h-4" />
                            </span>
                            <h4 class="text-sm font-bold text-slate-900">Submit Leave Application</h4>
                        </div>
                        <button wire:click="closeApplyModal" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <form wire:submit="submitLeaveApplication" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Leave Category</label>
                            <select wire:model="selectedLeaveTypeId"
                                    class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 text-slate-800 p-3 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all"
                                    required>
                                <option value="">-- Choose Leave Category --</option>
                                @foreach($this->leaveTypes as $lt)
                                    <option value="{{ $lt->id }}">{{ $lt->name }} ({{ $lt->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Start Date</label>
                                <input type="date"
                                       wire:model="startDate"
                                       class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 text-slate-800 p-3 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all"
                                       required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">End Date</label>
                                <input type="date"
                                       wire:model="endDate"
                                       class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 text-slate-800 p-3 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all"
                                       required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Reason for Leave</label>
                            <textarea wire:model="leaveReason"
                                      rows="3"
                                      class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 text-slate-800 p-3 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all"
                                      placeholder="State reason for absence..."
                                      required></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" wire:click="closeApplyModal" class="px-4 py-2 text-xs font-bold text-slate-600 rounded-xl hover:bg-slate-100 transition-all cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    class="px-5 py-2.5 text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 rounded-xl transition-all shadow-sm active:scale-[0.99] cursor-pointer">
                                <span wire:loading.remove wire:target="submitLeaveApplication">Submit Application</span>
                                <span wire:loading wire:target="submitLeaveApplication" class="inline-flex items-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    Submitting Application...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
