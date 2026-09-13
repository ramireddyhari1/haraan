<x-filament-panels::page>
    @php
        $requests = $this->requests;
    @endphp

    <div class="space-y-6">
        <div class="hrn-card p-5">
            <h3 class="text-base font-bold text-[var(--hrn-ink)]">Venue Staff Leave Review</h3>
            <p class="text-xs text-[var(--hrn-ink-2)]">Review, grant, or decline leave requests from your assigned venue team.</p>
        </div>

        <div class="hrn-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-[var(--hrn-track)]/60 text-[var(--hrn-ink-2)] uppercase text-[10px] font-extrabold tracking-wider border-b border-[var(--hrn-border)]">
                        <tr>
                            <th class="p-3.5">Staff Member</th>
                            <th class="p-3.5">Category</th>
                            <th class="p-3.5">Date Range</th>
                            <th class="p-3.5">Duration</th>
                            <th class="p-3.5">Reason</th>
                            <th class="p-3.5">Status</th>
                            <th class="p-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--hrn-border)] text-[var(--hrn-ink)]">
                        @forelse($requests as $req)
                            <tr class="hover:bg-[var(--hrn-track)]/20 transition-colors">
                                <td class="p-3.5 font-bold">
                                    {{ $req->employee?->full_name }}
                                    <span class="text-[10px] font-mono text-[var(--hrn-ink-3)] block">{{ $req->employee?->employee_code }}</span>
                                </td>
                                <td class="p-3.5">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-blue-500/15 text-blue-700 dark:text-blue-300">
                                        {{ $req->leaveType?->name }}
                                    </span>
                                </td>
                                <td class="p-3.5 font-mono">
                                    {{ \Illuminate\Support\Carbon::parse($req->start_date)->format('M d') }} &rarr; {{ \Illuminate\Support\Carbon::parse($req->end_date)->format('M d, Y') }}
                                </td>
                                <td class="p-3.5 font-bold">
                                    {{ $req->total_days }}d
                                </td>
                                <td class="p-3.5 text-[var(--hrn-ink-2)] max-w-xs truncate">
                                    {{ $req->reason }}
                                </td>
                                <td class="p-3.5">
                                    @if($req->status === 'approved')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 uppercase">Approved</span>
                                    @elseif($req->status === 'rejected')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/15 text-rose-700 dark:text-rose-300 uppercase">Declined</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 uppercase">Pending</span>
                                    @endif
                                </td>
                                <td class="p-3.5 text-right">
                                    @if($req->status === 'pending')
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button wire:click="approveRequest({{ $req->id }})"
                                                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold text-white bg-emerald-600 hover:bg-emerald-500 transition-all">
                                                Approve
                                            </button>
                                            <button wire:click="rejectRequest({{ $req->id }})"
                                                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold text-rose-600 bg-rose-500/10 hover:bg-rose-500/20 transition-all">
                                                Decline
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-[11px] text-[var(--hrn-ink-3)] italic">Resolved</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-xs text-[var(--hrn-ink-3)] italic">
                                    No pending leave requests from your venue team.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
