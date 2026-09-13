<x-filament-panels::page>
    @php
        $records = $this->attendances;
        $regsByDate = $this->regularisationsByDate;
        $presentCount = 0;
        $lateCount = 0;
        $halfDayCount = 0;
        $totalMinutes = 0;

        foreach ($records as $r) {
            if ($r->status === 'present') $presentCount++;
            elseif ($r->status === 'late') $lateCount++;
            elseif ($r->status === 'half_day') $halfDayCount++;
            $totalMinutes += (int)$r->total_work_minutes;
        }

        $totalHours = floor($totalMinutes / 60);
        $remMinutes = $totalMinutes % 60;
    @endphp

    <div class="space-y-6">
        {{-- Header & Stats Summary --}}
        <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 sm:p-7">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100">
                <div>
                    <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight">Monthly Attendance Ledger</h2>
                    <p class="text-xs text-slate-500 font-medium mt-0.5">Daily punch timestamps, GPS geofence verifications, and total on-duty hours.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <input type="month"
                           wire:model.live="selectedMonth"
                           class="text-xs rounded-xl border border-slate-200/80 bg-white text-slate-900 px-3 py-2 font-bold focus:ring-2 focus:ring-emerald-500">

                    <button wire:click="openRegularisationModal"
                            type="button"
                            class="min-h-[40px] px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 active:scale-[0.99]">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                        <span>Request Correction</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-5">
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Present Days</span>
                    <span class="text-2xl font-black text-emerald-700 font-mono">{{ $presentCount }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Late Punches</span>
                    <span class="text-2xl font-black text-amber-700 font-mono">{{ $lateCount }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Half Days</span>
                    <span class="text-2xl font-black text-slate-700 font-mono">{{ $halfDayCount }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Total Work Hours</span>
                    <span class="text-2xl font-black text-slate-900 font-mono">{{ $totalHours }}h {{ $remMinutes }}m</span>
                </div>
            </div>
        </div>

        {{-- Log Table Surface --}}
        <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-sm font-black text-slate-900">Daily Punch Timeline</h3>
                <span class="text-xs text-slate-400 font-medium">{{ count($records) }} log entries</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-extrabold tracking-wider border-b border-slate-100">
                        <tr>
                            <th class="p-4">Date</th>
                            <th class="p-4">Shift</th>
                            <th class="p-4">Clock In</th>
                            <th class="p-4">Clock Out</th>
                            <th class="p-4">Net Work</th>
                            <th class="p-4">Break</th>
                            <th class="p-4">Geofence</th>
                            <th class="p-4">Status / Correction</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        @forelse($records as $rec)
                            @php
                                $dStr = \Illuminate\Support\Carbon::parse($rec->date)->toDateString();
                                $reg = $regsByDate[$dStr] ?? null;
                            @endphp
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="p-4 font-bold text-slate-900 whitespace-nowrap">
                                    {{ \Illuminate\Support\Carbon::parse($rec->date)->format('D, M d') }}
                                </td>
                                <td class="p-4 text-slate-600 whitespace-nowrap">
                                    {{ $rec->shift?->name ?? 'Standard' }}
                                </td>
                                <td class="p-4 font-mono whitespace-nowrap">
                                    {{ $rec->clock_in_at ? $rec->clock_in_at->format('h:i A') : '—' }}
                                </td>
                                <td class="p-4 font-mono whitespace-nowrap">
                                    {{ $rec->clock_out_at ? $rec->clock_out_at->format('h:i A') : '—' }}
                                </td>
                                <td class="p-4 font-bold font-mono whitespace-nowrap">
                                    {{ $rec->formatted_work_duration }}
                                </td>
                                <td class="p-4 text-slate-500 whitespace-nowrap">
                                    {{ $rec->total_break_minutes ? $rec->total_break_minutes . 'm' : '0m' }}
                                </td>
                                <td class="p-4 whitespace-nowrap">
                                    @if($rec->clock_in_geofence_status === 'inside')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200/80">
                                            ✓ Inside ({{ number_format((float)$rec->clock_in_distance_meters, 0) }}m)
                                        </span>
                                    @elseif($rec->clock_in_geofence_status === 'outside')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200/80">
                                            ⚠ Outside ({{ number_format((float)$rec->clock_in_distance_meters, 0) }}m)
                                        </span>
                                    @else
                                        <span class="text-[11px] text-slate-400">Exempt</span>
                                    @endif
                                </td>
                                <td class="p-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        @if($rec->status === 'present')
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200/80 uppercase">Present</span>
                                        @elseif($rec->status === 'late')
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200/80 uppercase">Late</span>
                                        @elseif($rec->status === 'half_day')
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200/80 uppercase">Half Day</span>
                                        @elseif($rec->status === 'on_leave')
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-800 border border-purple-200/80 uppercase">On Leave</span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 uppercase">{{ $rec->status }}</span>
                                        @endif

                                        @if($reg)
                                            @if($reg->status === 'pending')
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-200">
                                                    Correction Pending
                                                </span>
                                            @elseif($reg->status === 'approved')
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-200">
                                                    Regularised
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                                <td class="p-4 text-right whitespace-nowrap">
                                    <button wire:click="openRegularisationModal('{{ $dStr }}')"
                                            type="button"
                                            class="text-xs font-bold text-slate-600 hover:text-emerald-700 hover:underline">
                                        Dispute / Fix
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="p-8 text-center text-xs text-slate-400 italic">
                                    No punch logs recorded for {{ date('F Y', strtotime($selectedMonth . '-01')) }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Regularisation Request Modal --}}
    @if($showRegularisationModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Request Punch Regularisation</h3>
                        <p class="text-xs text-slate-500">Submit an attendance adjustment for manager review.</p>
                    </div>
                    <button wire:click="closeRegularisationModal" type="button" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Target Date</label>
                        <input type="date" wire:model="regDate" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-800">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Corrected Clock In</label>
                            <input type="time" wire:model="regClockIn" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-mono font-bold text-slate-800">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Corrected Clock Out</label>
                            <input type="time" wire:model="regClockOut" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-mono font-bold text-slate-800">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Dispute Reason Category</label>
                        <select wire:model="regCategory" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-800 bg-white">
                            <option value="missed_punch">Forgot to punch in/out</option>
                            <option value="gps_drift">GPS / Location drift error</option>
                            <option value="outdoor_duty">External Arena / Field assignment</option>
                            <option value="biometric_error">Biometric / Device technical failure</option>
                            <option value="other">Other operational reason</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Detailed Explanation</label>
                        <textarea wire:model="regReason" rows="3" placeholder="Explain what occurred..." class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button wire:click="closeRegularisationModal" type="button" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100">
                        Cancel
                    </button>
                    <button wire:click="submitRegularisation" type="button" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 shadow-xs">
                        Submit Request
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
