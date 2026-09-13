<x-filament-panels::page>
    @php
        $balances = $this->balances;
        $requests = $this->requests;
    @endphp

    <div class="space-y-6">
        {{-- Quota Overview Header --}}
        <div class="hrn-card p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[var(--hrn-border)] mb-4">
                <div>
                    <h3 class="text-base font-bold text-[var(--hrn-ink)]">Annual Leave Balance &amp; Policies</h3>
                    <p class="text-xs text-[var(--hrn-ink-2)]">Track your remaining quotas, pending requests, and used leave days.</p>
                </div>

                <button wire:click="openApplyModal"
                        type="button"
                        class="py-2.5 px-4 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 shadow-md shadow-emerald-600/20 transition-all flex items-center gap-1.5">
                    <x-heroicon-m-plus class="w-4 h-4" />
                    <span>Apply for Leave</span>
                </button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach($balances as $bal)
                    <div class="p-3.5 rounded-xl bg-[var(--hrn-track)]/40 border border-[var(--hrn-border)]">
                        <div class="flex items-center justify-between text-xs font-bold text-[var(--hrn-ink)] mb-1">
                            <span>{{ $bal->leaveType?->code }}</span>
                            <span class="text-emerald-600 dark:text-emerald-400 text-sm font-extrabold">{{ $bal->remaining_days }}d left</span>
                        </div>
                        <div class="text-[11px] text-[var(--hrn-ink-2)] truncate">{{ $bal->leaveType?->name }}</div>
                        <div class="flex items-center justify-between text-[10px] text-[var(--hrn-ink-3)] mt-2 pt-2 border-t border-[var(--hrn-border)]">
                            <span>Allocated: {{ $bal->allocated_days }}d</span>
                            <span>Used: {{ $bal->used_days }}d</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Requests History Table --}}
        <div class="hrn-card overflow-hidden">
            <div class="p-4 border-b border-[var(--hrn-border)]">
                <h4 class="text-sm font-bold text-[var(--hrn-ink)]">Leave Application History</h4>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-[var(--hrn-track)]/60 text-[var(--hrn-ink-2)] uppercase text-[10px] font-extrabold tracking-wider border-b border-[var(--hrn-border)]">
                        <tr>
                            <th class="p-3.5">Category</th>
                            <th class="p-3.5">Date Range</th>
                            <th class="p-3.5">Days</th>
                            <th class="p-3.5">Reason</th>
                            <th class="p-3.5">Supervisor Notes</th>
                            <th class="p-3.5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--hrn-border)] text-[var(--hrn-ink)]">
                        @forelse($requests as $req)
                            <tr class="hover:bg-[var(--hrn-track)]/20 transition-colors">
                                <td class="p-3.5 font-bold">
                                    {{ $req->leaveType?->name }}
                                </td>
                                <td class="p-3.5 font-mono">
                                    {{ \Illuminate\Support\Carbon::parse($req->start_date)->format('M d, Y') }} &rarr; {{ \Illuminate\Support\Carbon::parse($req->end_date)->format('M d, Y') }}
                                </td>
                                <td class="p-3.5 font-bold">
                                    {{ $req->total_days }} day(s)
                                </td>
                                <td class="p-3.5 text-[var(--hrn-ink-2)] max-w-xs truncate">
                                    {{ $req->reason }}
                                </td>
                                <td class="p-3.5 text-[var(--hrn-ink-3)] italic">
                                    {{ $req->approver_notes ?? '—' }}
                                </td>
                                <td class="p-3.5 text-right">
                                    @if($req->status === 'approved')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 uppercase">Approved</span>
                                    @elseif($req->status === 'rejected')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/15 text-rose-700 dark:text-rose-300 uppercase">Rejected</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 uppercase">Pending Review</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-xs text-[var(--hrn-ink-3)] italic">
                                    No leave requests submitted yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Apply Modal --}}
        @if($showApplyModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                <div class="w-full max-w-md rounded-2xl bg-[var(--hrn-surface)] border border-[var(--hrn-border)] p-6 shadow-2xl">
                    <div class="flex items-center justify-between pb-3 border-b border-[var(--hrn-border)] mb-4">
                        <h4 class="text-sm font-bold text-[var(--hrn-ink)]">Submit Leave Application</h4>
                        <button wire:click="closeApplyModal" class="text-[var(--hrn-ink-3)] hover:text-[var(--hrn-ink)]">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <form wire:submit="submitApplication" class="space-y-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Leave Category</label>
                            <select wire:model="selectedLeaveTypeId"
                                    class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5"
                                    required>
                                <option value="">-- Choose Category --</option>
                                @foreach($this->leaveTypes as $lt)
                                    <option value="{{ $lt->id }}">{{ $lt->name }} ({{ $lt->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Start Date</label>
                                <input type="date" wire:model="startDate" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">End Date</label>
                                <input type="date" wire:model="endDate" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Reason for Absence</label>
                            <textarea wire:model="leaveReason" rows="3" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" placeholder="State reason..." required></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" wire:click="closeApplyModal" class="px-4 py-2 text-xs font-bold text-[var(--hrn-ink-2)] rounded-xl hover:bg-[var(--hrn-track)]">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl">
                                Submit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
