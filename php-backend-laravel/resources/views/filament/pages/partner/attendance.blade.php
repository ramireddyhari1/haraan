<x-filament-panels::page>
    @php
        $records = $this->attendances;
    @endphp

    <div class="space-y-6">
        <div class="hrn-card p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-[var(--hrn-ink)]">Floor Team Live Attendance</h3>
                <p class="text-xs text-[var(--hrn-ink-2)]">Real-time status of staff on duty, break timers, and GPS geofence radar verification.</p>
            </div>

            <div class="flex items-center gap-2">
                <label class="text-xs font-semibold text-[var(--hrn-ink-2)]">Date:</label>
                <input type="date"
                       wire:model.live="filterDate"
                       class="text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] px-3 py-2 font-bold">
            </div>
        </div>

        <div class="hrn-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-[var(--hrn-track)]/60 text-[var(--hrn-ink-2)] uppercase text-[10px] font-extrabold tracking-wider border-b border-[var(--hrn-border)]">
                        <tr>
                            <th class="p-3.5">Staff Member</th>
                            <th class="p-3.5">Shift</th>
                            <th class="p-3.5">Clock In</th>
                            <th class="p-3.5">Clock Out</th>
                            <th class="p-3.5">Duration</th>
                            <th class="p-3.5">Geofence Distance</th>
                            <th class="p-3.5 text-right">Floor Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--hrn-border)] text-[var(--hrn-ink)]">
                        @forelse($records as $rec)
                            <tr class="hover:bg-[var(--hrn-track)]/20 transition-colors">
                                <td class="p-3.5 font-bold">
                                    {{ $rec->employee?->full_name }}
                                    <span class="text-[10px] font-mono text-[var(--hrn-ink-3)] block">{{ $rec->employee?->employee_code }}</span>
                                </td>
                                <td class="p-3.5 text-[var(--hrn-ink-2)]">
                                    {{ $rec->shift?->name ?? 'Standard Shift' }}
                                </td>
                                <td class="p-3.5 font-mono">
                                    {{ $rec->clock_in_at ? $rec->clock_in_at->format('h:i A') : '—' }}
                                </td>
                                <td class="p-3.5 font-mono">
                                    {{ $rec->clock_out_at ? $rec->clock_out_at->format('h:i A') : 'Active' }}
                                </td>
                                <td class="p-3.5 font-bold">
                                    {{ $rec->formatted_work_duration }}
                                </td>
                                <td class="p-3.5">
                                    @if($rec->clock_in_geofence_status === 'inside')
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                            ✓ Verified ({{ number_format((float)$rec->clock_in_distance_meters, 0) }}m)
                                        </span>
                                    @elseif($rec->clock_in_geofence_status === 'outside')
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                            ⚠ Outside ({{ number_format((float)$rec->clock_in_distance_meters, 0) }}m)
                                        </span>
                                    @else
                                        <span class="text-[10px] text-[var(--hrn-ink-3)]">Exempt</span>
                                    @endif
                                </td>
                                <td class="p-3.5 text-right">
                                    @if($rec->isCurrentlyOnBreak())
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 uppercase">On Break</span>
                                    @elseif($rec->isCurrentlyClockedIn())
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 uppercase">On Duty</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-500/15 text-slate-700 dark:text-slate-300 uppercase">Shift Ended</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-xs text-[var(--hrn-ink-3)] italic">
                                    No attendance records found for this date.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
