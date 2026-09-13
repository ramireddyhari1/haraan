<x-filament-panels::page>
    @php
        $reportees = $this->reportees;
        $teamStatus = $this->teamStatus;
        $pendingLeaves = $this->pendingLeaves;
        $pendingRegs = $this->pendingRegularisations;
        $pendingSwaps = $this->pendingSwaps;
        $activityEvents = $this->activityEvents;

        $activeDutyCount = collect($teamStatus)->where('status', 'active')->count();
        $onBreakCount = collect($teamStatus)->where('status', 'on_break')->count();
        $totalReportees = count($reportees);
    @endphp

    <div class="space-y-6">
        {{-- Manager Header Surface --}}
        <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 sm:p-7">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                <div class="flex items-start sm:items-center gap-4">
                    <div class="w-13 h-13 rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-800 text-white font-black text-lg flex items-center justify-center shadow-sm shadow-emerald-600/20 ring-4 ring-emerald-50/80 shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Team Operations Console</h1>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200/80">MSS</span>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-500 font-medium">
                            Supervising {{ $totalReportees }} direct team members across assigned venue operations.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button wire:click="openTaskModal"
                            type="button"
                            class="min-h-[44px] px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs hover:shadow transition-all flex items-center gap-2 active:scale-[0.99]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        <span>Delegate Task</span>
                    </button>
                </div>
            </div>

            {{-- Live Status Metric Pills --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6 pt-5 border-t border-slate-100">
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block mb-1">Total Team</span>
                    <span class="text-2xl font-black text-slate-900 font-mono">{{ $totalReportees }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-emerald-50/50 border border-emerald-200/70">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 block mb-1">Active on Duty</span>
                    <span class="text-2xl font-black text-emerald-700 font-mono">{{ $activeDutyCount }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-amber-50/50 border border-amber-200/70">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-amber-700 block mb-1">On Break</span>
                    <span class="text-2xl font-black text-amber-700 font-mono">{{ $onBreakCount }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-indigo-50/50 border border-indigo-200/70">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-700 block mb-1">Pending Approvals</span>
                    <span class="text-2xl font-black text-indigo-700 font-mono">{{ count($pendingLeaves) + count($pendingRegs) + count($pendingSwaps) }}</span>
                </div>
            </div>
        </div>

        {{-- Nav Segmented Tabs --}}
        <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none no-scrollbar">
            <button wire:click="setTab('overview')"
                    class="min-h-[44px] px-4 py-2.5 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-2 {{ $activeTab === 'overview' ? 'bg-slate-900 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200/80 hover:bg-slate-50' }}">
                <span>Live Roster & Duty</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] {{ $activeTab === 'overview' ? 'bg-slate-800 text-slate-200' : 'bg-slate-100 text-slate-600' }}">{{ $totalReportees }}</span>
            </button>

            <button wire:click="setTab('leaves')"
                    class="min-h-[44px] px-4 py-2.5 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-2 {{ $activeTab === 'leaves' ? 'bg-slate-900 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200/80 hover:bg-slate-50' }}">
                <span>Leave Approvals</span>
                @if(count($pendingLeaves) > 0)
                    <span class="px-1.5 py-0.5 rounded text-[10px] bg-rose-500 text-white font-black">{{ count($pendingLeaves) }}</span>
                @endif
            </button>

            <button wire:click="setTab('regularisations')"
                    class="min-h-[44px] px-4 py-2.5 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-2 {{ $activeTab === 'regularisations' ? 'bg-slate-900 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200/80 hover:bg-slate-50' }}">
                <span>Punch Regularisations</span>
                @if(count($pendingRegs) > 0)
                    <span class="px-1.5 py-0.5 rounded text-[10px] bg-amber-500 text-white font-black">{{ count($pendingRegs) }}</span>
                @endif
            </button>

            <button wire:click="setTab('swaps')"
                    class="min-h-[44px] px-4 py-2.5 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-2 {{ $activeTab === 'swaps' ? 'bg-slate-900 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200/80 hover:bg-slate-50' }}">
                <span>Shift Swaps</span>
                @if(count($pendingSwaps) > 0)
                    <span class="px-1.5 py-0.5 rounded text-[10px] bg-indigo-500 text-white font-black">{{ count($pendingSwaps) }}</span>
                @endif
            </button>

            <button wire:click="setTab('activity')"
                    class="min-h-[44px] px-4 py-2.5 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-2 {{ $activeTab === 'activity' ? 'bg-slate-900 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200/80 hover:bg-slate-50' }}">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 motion-reduce:animate-none animate-pulse"></span>
                    <span>Live Operations Stream</span>
                </span>
                <span class="px-1.5 py-0.5 rounded text-[10px] {{ $activeTab === 'activity' ? 'bg-slate-800 text-slate-200' : 'bg-slate-100 text-slate-600' }}">{{ count($activityEvents) }}</span>
            </button>
        </div>

        {{-- TAB 1: Team Live Overview --}}
        @if($activeTab === 'overview')
            <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h2 class="text-base font-black text-slate-900">Today's Team Shift Roster</h2>
                        <p class="text-xs text-slate-500">Live check-in telemetry and shift assignment status for {{ now()->format('l, d M Y') }}</p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($teamStatus as $item)
                        @php
                            $emp = $item['employee'];
                            $st = $item['status'];
                            $det = $item['details'];
                            $att = $item['attendance'];
                            $rost = $item['roster'];
                        @endphp
                        <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 first:pt-0 last:pb-0">
                            <div class="flex items-center gap-3.5 min-w-0">
                                <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 font-bold text-sm flex items-center justify-center shrink-0 border border-slate-200/80">
                                    {{ strtoupper(substr($emp->user?->name ?? 'EM', 0, 2)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-bold text-slate-900 truncate">{{ $emp->user?->name ?? $emp->employee_code }}</h3>
                                        <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">{{ $emp->employee_code }}</span>
                                    </div>
                                    <div class="text-xs text-slate-500 truncate mt-0.5">
                                        <span>{{ $emp->designation?->name ?? 'Staff' }}</span>
                                        <span class="text-slate-300 mx-1">•</span>
                                        <span>{{ $emp->venue?->name ?? 'HQ' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0">
                                <div class="text-right">
                                    @if($st === 'active')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200/80">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500 motion-reduce:animate-none animate-pulse"></span>
                                            <span>Active On Duty</span>
                                        </span>
                                    @elseif($st === 'on_break')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200/80">
                                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                            <span>On Break</span>
                                        </span>
                                    @elseif($st === 'concluded')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                            Concluded
                                        </span>
                                    @elseif($st === 'on_leave')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                            On Leave
                                        </span>
                                    @elseif($st === 'scheduled')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                            Scheduled
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            Absent / Unpunched
                                        </span>
                                    @endif
                                    <div class="text-[11px] text-slate-400 font-mono mt-1">{{ $det }}</div>
                                </div>

                                <button wire:click="openTaskModal({{ $emp->id }})"
                                        title="Delegate Task"
                                        type="button"
                                        class="w-9 h-9 rounded-xl border border-slate-200/80 hover:border-emerald-500 hover:text-emerald-700 text-slate-500 flex items-center justify-center transition-all">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-slate-400">
                            No active reportees assigned under your profile.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- TAB 2: Leave Approvals --}}
        @if($activeTab === 'leaves')
            <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 space-y-4">
                <div class="pb-3 border-b border-slate-100">
                    <h2 class="text-base font-black text-slate-900">Pending Leave Requests</h2>
                    <p class="text-xs text-slate-500">Review and authorize team member leave applications</p>
                </div>

                <div class="space-y-3">
                    @forelse($pendingLeaves as $req)
                        <div class="p-4 rounded-xl bg-slate-50/70 border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900">{{ $req->employee?->full_name }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">{{ $req->leaveType?->name }}</span>
                                    <span class="text-xs font-mono font-bold text-slate-600">{{ $req->total_days }} day(s)</span>
                                </div>
                                <div class="text-xs text-slate-500 font-mono">
                                    {{ \Illuminate\Support\Carbon::parse($req->start_date)->format('M d, Y') }} &rarr; {{ \Illuminate\Support\Carbon::parse($req->end_date)->format('M d, Y') }}
                                </div>
                                <p class="text-xs text-slate-700 italic bg-white p-2 rounded-lg border border-slate-200/60 max-w-xl">
                                    "{{ $req->reason }}"
                                </p>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <button wire:click="rejectLeave({{ $req->id }})"
                                        type="button"
                                        class="min-h-[40px] px-3.5 py-2 rounded-xl border border-rose-200 text-rose-700 bg-white hover:bg-rose-50 text-xs font-bold transition-all">
                                    Reject
                                </button>
                                <button wire:click="approveLeave({{ $req->id }})"
                                        type="button"
                                        class="min-h-[40px] px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-xs">
                                    Approve
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-slate-400">
                            No pending leave requests awaiting approval.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- TAB 3: Punch Regularisations --}}
        @if($activeTab === 'regularisations')
            <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 space-y-4">
                <div class="pb-3 border-b border-slate-100">
                    <h2 class="text-base font-black text-slate-900">Attendance Punch Regularisations</h2>
                    <p class="text-xs text-slate-500">Authorize punch dispute adjustments and missed-punch corrections</p>
                </div>

                <div class="space-y-3">
                    @forelse($pendingRegs as $reg)
                        <div class="p-4 rounded-xl bg-slate-50/70 border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="space-y-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900">{{ $reg->employee?->full_name }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 uppercase">{{ str_replace('_', ' ', $reg->reason_category) }}</span>
                                    <span class="text-xs font-bold text-slate-700">{{ \Illuminate\Support\Carbon::parse($reg->date)->format('D, M d, Y') }}</span>
                                    @if($reg->approval_tier > 1)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800">Tier 2 Required</span>
                                    @endif
                                    @if($reg->status === 'tier2_pending')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800">Awaiting GM (Tier 2)</span>
                                    @elseif($reg->status === 'escalated')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 animate-pulse">SLA Breach Escalated</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 font-mono">
                                    Requested: {{ \Illuminate\Support\Carbon::parse($reg->requested_clock_in_at)->format('h:i A') }} &rarr; {{ \Illuminate\Support\Carbon::parse($reg->requested_clock_out_at)->format('h:i A') }}
                                </div>
                                <p class="text-xs text-slate-700 italic bg-white p-2 rounded-lg border border-slate-200/60 max-w-xl">
                                    "{{ $reg->reason }}"
                                </p>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <button wire:click="rejectRegularisation({{ $reg->id }})"
                                        type="button"
                                        class="min-h-[40px] px-3.5 py-2 rounded-xl border border-rose-200 text-rose-700 bg-white hover:bg-rose-50 text-xs font-bold transition-all">
                                    Decline
                                </button>
                                <button wire:click="approveRegularisation({{ $reg->id }})"
                                        type="button"
                                        class="min-h-[40px] px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-xs">
                                    Confirm Correction
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-slate-400">
                            No attendance regularisation requests pending review.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- TAB 4: Shift Swaps --}}
        @if($activeTab === 'swaps')
            <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 space-y-4">
                <div class="pb-3 border-b border-slate-100">
                    <h2 class="text-base font-black text-slate-900">Shift Swap Authorization</h2>
                    <p class="text-xs text-slate-500">Sign off on shift trades between assigned team members</p>
                </div>

                <div class="space-y-3">
                    @forelse($pendingSwaps as $swap)
                        <div class="p-4 rounded-xl bg-slate-50/70 border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="space-y-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900">{{ $swap->requestorRoster?->employee?->full_name }}</span>
                                    <span class="text-slate-400">&harr;</span>
                                    <span class="text-sm font-bold text-slate-900">{{ $swap->targetEmployee?->full_name }}</span>
                                </div>
                                <div class="text-xs text-slate-500 font-mono">
                                    Shift Date: {{ \Illuminate\Support\Carbon::parse($swap->requestorRoster?->roster_date)->format('M d, Y') }} ({{ $swap->requestorRoster?->shift?->name }})
                                </div>
                                @if($swap->reason)
                                    <p class="text-xs text-slate-600 italic bg-white p-2 rounded-lg border border-slate-200/60 max-w-xl">
                                        "{{ $swap->reason }}"
                                    </p>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <button wire:click="rejectSwap({{ $swap->id }})"
                                        type="button"
                                        class="min-h-[40px] px-3.5 py-2 rounded-xl border border-rose-200 text-rose-700 bg-white hover:bg-rose-50 text-xs font-bold transition-all">
                                    Decline
                                </button>
                                <button wire:click="approveSwap({{ $swap->id }})"
                                        type="button"
                                        class="min-h-[40px] px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-xs">
                                    Authorize Swap
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-slate-400">
                            No shift swap requests awaiting manager sign-off.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- TAB 5: Live Operations Stream & Audit Timeline --}}
        @if($activeTab === 'activity')
            <div wire:poll.15s class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-slate-100">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-black text-slate-900">Live Team Operations Telemetry</h2>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 motion-reduce:animate-none animate-ping"></span>
                                Live Sync (15s)
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Real-time immutable audit stream of punches, geofence validations, approvals, and field actions</p>
                    </div>
                    <div class="text-xs font-mono text-slate-400">
                        {{ count($activityEvents) }} events captured
                    </div>
                </div>

                <div class="relative pl-6 space-y-6 before:absolute before:top-2 before:bottom-2 before:left-[11px] before:w-[2px] before:bg-slate-100">
                    @forelse($activityEvents as $ev)
                        @php
                            $isPunchIn = str_contains($ev->event_name, 'PUNCH_IN');
                            $isPunchOut = str_contains($ev->event_name, 'PUNCH_OUT');
                            $isApproval = str_contains($ev->event_name, 'APPROVED');
                            $isRejection = str_contains($ev->event_name, 'REJECTED');
                            $isEscalated = str_contains($ev->event_name, 'ESCALATED');

                            $dotColor = 'bg-slate-400 ring-slate-100';
                            $badgeColor = 'bg-slate-100 text-slate-700 border-slate-200';

                            if ($isPunchIn) {
                                $dotColor = 'bg-emerald-500 ring-emerald-100';
                                $badgeColor = 'bg-emerald-50 text-emerald-800 border-emerald-200/80';
                            } elseif ($isPunchOut) {
                                $dotColor = 'bg-blue-500 ring-blue-100';
                                $badgeColor = 'bg-blue-50 text-blue-800 border-blue-200/80';
                            } elseif ($isApproval) {
                                $dotColor = 'bg-teal-500 ring-teal-100';
                                $badgeColor = 'bg-teal-50 text-teal-800 border-teal-200/80';
                            } elseif ($isRejection) {
                                $dotColor = 'bg-rose-500 ring-rose-100';
                                $badgeColor = 'bg-rose-50 text-rose-800 border-rose-200/80';
                            } elseif ($isEscalated) {
                                $dotColor = 'bg-amber-500 ring-amber-100';
                                $badgeColor = 'bg-amber-50 text-amber-800 border-amber-200/80';
                            }
                        @endphp
                        <div class="relative flex items-start gap-4 group">
                            {{-- Timeline Node Dot --}}
                            <div class="absolute -left-6 mt-1 w-3.5 h-3.5 rounded-full {{ $dotColor }} ring-4 bg-white shrink-0"></div>

                            <div class="flex-1 bg-slate-50/60 rounded-xl p-4 border border-slate-200/70 hover:border-slate-300 transition-all">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-bold text-slate-900">{{ $ev->actor?->name ?? 'System' }}</span>
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-200/70 text-slate-700 font-semibold">{{ $ev->actor_role ?? 'USER' }}</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $badgeColor }}">
                                            {{ str_replace('_', ' ', $ev->event_name) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 text-slate-400 text-[11px] font-mono">
                                        <span>{{ $ev->occurred_at ? $ev->occurred_at->diffForHumans() : 'Just now' }}</span>
                                        <span class="text-slate-300">•</span>
                                        <span>{{ $ev->occurred_at ? $ev->occurred_at->format('H:i:s') : '' }}</span>
                                    </div>
                                </div>

                                {{-- Telemetry Details & Metadata --}}
                                @if(!empty($ev->telemetry_metadata))
                                    <div class="flex flex-wrap items-center gap-2 mt-2 pt-2 border-t border-slate-200/50 text-[11px] text-slate-600">
                                        @if(isset($ev->telemetry_metadata['method']))
                                            <span class="inline-flex items-center gap-1 font-mono text-[10px] px-1.5 py-0.5 rounded bg-white border border-slate-200">
                                                <span class="text-slate-400">Via:</span> {{ strtoupper($ev->telemetry_metadata['method']) }}
                                            </span>
                                        @endif
                                        @if(isset($ev->telemetry_metadata['geofence_status']))
                                            <span class="inline-flex items-center gap-1 font-mono text-[10px] px-1.5 py-0.5 rounded {{ in_array($ev->telemetry_metadata['geofence_status'], ['inside', 'in_geofence']) ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200' }} border">
                                                <span>{{ $ev->telemetry_metadata['geofence_status'] }}</span>
                                            </span>
                                        @endif
                                        @if(isset($ev->telemetry_metadata['idempotency_key']))
                                            <span class="font-mono text-[10px] text-slate-400 truncate max-w-[200px]" title="{{ $ev->telemetry_metadata['idempotency_key'] }}">
                                                Key: {{ substr($ev->telemetry_metadata['idempotency_key'], 0, 8) }}...
                                            </span>
                                        @endif
                                        @if($ev->signature_hash)
                                            <span class="ml-auto font-mono text-[10px] text-slate-400" title="HMAC Signature: {{ $ev->signature_hash }}">
                                                Sig: {{ substr($ev->signature_hash, 0, 10) }}...
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-xs text-slate-400">
                            No telemetry activity recorded for team members yet.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>

    {{-- Delegate Task Modal --}}
    @if($showTaskModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-black text-slate-900">Delegate Floor Task</h3>
                    <button wire:click="closeTaskModal" type="button" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Assignee</label>
                        <select wire:model="selectedEmployeeId" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-800 bg-white">
                            @foreach($reportees as $r)
                                <option value="{{ $r->id }}">{{ $r->full_name }} ({{ $r->designation?->name ?? 'Staff' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Task Title</label>
                        <input type="text" wire:model="taskTitle" placeholder="e.g. Inspect Court 3 Turf Lighting..." class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Priority</label>
                            <select wire:model="taskPriority" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-800 bg-white">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Due Date</label>
                            <input type="date" wire:model="taskDueDate" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-800">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Instructions / Description</label>
                        <textarea wire:model="taskDescription" rows="3" placeholder="Provide checklist or details..." class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button wire:click="closeTaskModal" type="button" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100">
                        Cancel
                    </button>
                    <button wire:click="submitTask" type="button" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-xs">
                        Assign Task
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
