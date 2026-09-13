<x-filament-panels::page>
    @php
        $rosters = $this->rosters;
    @endphp

    <div class="space-y-6">
        <div class="hrn-card p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-[var(--hrn-ink)]">Venue Team Shift Roster</h3>
                <p class="text-xs text-[var(--hrn-ink-2)]">Schedule and dispatch morning, evening, and rotational shifts to your floor staff.</p>
            </div>

            <div class="flex items-center gap-3">
                <input type="date"
                       wire:model.live="selectedDate"
                       class="text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] px-3 py-2 font-bold">

                <button wire:click="openAssignModal"
                        type="button"
                        class="py-2 px-4 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-500 shadow-md shadow-blue-600/20 transition-all flex items-center gap-1.5">
                    <x-heroicon-m-plus class="w-4 h-4" />
                    <span>Assign Shift</span>
                </button>
            </div>
        </div>

        <div class="hrn-card overflow-hidden">
            <div class="p-4 border-b border-[var(--hrn-border)] flex items-center justify-between">
                <span class="text-xs font-bold text-[var(--hrn-ink)]">
                    Roster for {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('l, F d, Y') }}
                </span>
                <span class="text-xs font-mono text-[var(--hrn-ink-3)]">{{ count($rosters) }} Scheduled</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-[var(--hrn-track)]/60 text-[var(--hrn-ink-2)] uppercase text-[10px] font-extrabold tracking-wider border-b border-[var(--hrn-border)]">
                        <tr>
                            <th class="p-3.5">Staff Member</th>
                            <th class="p-3.5">Assigned Shift</th>
                            <th class="p-3.5">Shift Timings</th>
                            <th class="p-3.5">Venue Location</th>
                            <th class="p-3.5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--hrn-border)] text-[var(--hrn-ink)]">
                        @forelse($rosters as $r)
                            <tr class="hover:bg-[var(--hrn-track)]/20 transition-colors">
                                <td class="p-3.5 font-bold">
                                    {{ $r->employee?->full_name }}
                                    <span class="text-[10px] font-mono text-[var(--hrn-ink-3)] block">{{ $r->employee?->employee_code }}</span>
                                </td>
                                <td class="p-3.5">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-blue-500/15 text-blue-700 dark:text-blue-300">
                                        {{ $r->shift?->name }}
                                    </span>
                                </td>
                                <td class="p-3.5 font-mono">
                                    {{ $r->shift?->formatted_timing }}
                                </td>
                                <td class="p-3.5 text-[var(--hrn-ink-2)]">
                                    {{ $r->venue?->name ?? 'All Courts' }}
                                </td>
                                <td class="p-3.5 text-right">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 uppercase">
                                        {{ $r->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-xs text-[var(--hrn-ink-3)] italic">
                                    No staff scheduled for this date. Click "+ Assign Shift" to plan duty rosters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Assign Modal --}}
        @if($showAssignModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                <div class="w-full max-w-md rounded-2xl bg-[var(--hrn-surface)] border border-[var(--hrn-border)] p-6 shadow-2xl">
                    <div class="flex items-center justify-between pb-3 border-b border-[var(--hrn-border)] mb-4">
                        <h4 class="text-sm font-bold text-[var(--hrn-ink)]">Schedule Shift Assignment</h4>
                        <button wire:click="closeAssignModal" class="text-[var(--hrn-ink-3)] hover:text-[var(--hrn-ink)]">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <form wire:submit="assignShift" class="space-y-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Select Staff Member</label>
                            <select wire:model="selectedEmployeeId" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" required>
                                <option value="">-- Choose Staff Member --</option>
                                @foreach($this->staff as $s)
                                    <option value="{{ $s->id }}">{{ $s->full_name }} ({{ $s->employee_code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Select Shift</label>
                            <select wire:model="selectedShiftId" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" required>
                                <option value="">-- Choose Shift --</option>
                                @foreach($this->shifts as $sh)
                                    <option value="{{ $sh->id }}">{{ $sh->name }} ({{ $sh->formatted_timing }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Venue / Branch (Optional)</label>
                            <select wire:model="selectedVenueId" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5">
                                <option value="">-- Primary Assigned Venue --</option>
                                @foreach($this->venues as $v)
                                    <option value="{{ $v->id }}">{{ $v->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" wire:click="closeAssignModal" class="px-4 py-2 text-xs font-bold text-[var(--hrn-ink-2)] rounded-xl hover:bg-[var(--hrn-track)]">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-500 rounded-xl">
                                Assign to Roster
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
