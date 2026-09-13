<x-filament-widgets::widget>
    @php
        $tasks = $this->tasks;
        $totalCount = count($tasks);
        $completedCount = collect($tasks)->filter(fn($t) => $t->status === 'completed')->count();
        $progressPct = $totalCount > 0 ? round(($completedCount / $totalCount) * 100) : 0;
    @endphp

    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 sm:p-7 transition-all flex flex-col justify-between h-full">
        <div>
            {{-- Header with Segmented Filter Control --}}
            <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 shrink-0">
                        <x-heroicon-o-clipboard-document-check class="w-5 h-5" />
                    </span>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-slate-900 tracking-tight">Floor Tasks &amp; Duties</h3>
                        <p class="text-[11px] text-slate-400 font-medium">Daily assigned checklist</p>
                    </div>
                </div>

                {{-- Status Filter Segmented Control --}}
                <div class="inline-flex items-center p-1 bg-slate-100 rounded-xl border border-slate-200/60 text-xs">
                    <button wire:click="$set('filterStatus', 'pending')"
                            type="button"
                            class="px-3 py-1 rounded-lg font-bold transition-all cursor-pointer {{ $filterStatus === 'pending' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800' }}">
                        To-Do
                    </button>
                    <button wire:click="$set('filterStatus', 'completed')"
                            type="button"
                            class="px-3 py-1 rounded-lg font-bold transition-all cursor-pointer {{ $filterStatus === 'completed' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800' }}">
                        Done
                    </button>
                </div>
            </div>

            {{-- Progress indicator if tasks exist --}}
            @if($totalCount > 0)
                <div class="mb-4">
                    <div class="flex items-center justify-between text-xs font-medium text-slate-500 mb-1.5">
                        <span>Checklist Completion</span>
                        <span class="font-bold text-slate-800 font-mono">{{ $completedCount }} / {{ $totalCount }} done ({{ $progressPct }}%)</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-emerald-600 h-1.5 rounded-full transition-all duration-500" style="width: {{ $progressPct }}%"></div>
                    </div>
                </div>
            @endif

            {{-- Task Items List (Flat list with hairline dividers) --}}
            <div class="divide-y divide-slate-100">
                @forelse($tasks as $task)
                    <div class="py-3 flex items-start gap-3 transition-all hover:bg-slate-50/60 px-1 rounded-lg">
                        <input type="checkbox"
                               wire:click="toggleTaskCompletion({{ $task->id }})"
                               {{ $task->status === 'completed' ? 'checked' : '' }}
                               class="mt-0.5 w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/20 cursor-pointer shrink-0">

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2 mb-0.5">
                                <span class="text-xs font-bold text-slate-900 truncate {{ $task->status === 'completed' ? 'line-through text-slate-400 font-medium' : '' }}">
                                    {{ $task->title }}
                                </span>

                                {{-- Priority Badge --}}
                                @if($task->priority === 'urgent')
                                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 border border-rose-200 shrink-0">Urgent</span>
                                @elseif($task->priority === 'high')
                                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200 shrink-0">High</span>
                                @else
                                    <span class="text-[9px] font-bold uppercase px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 border border-slate-200 shrink-0">{{ $task->priority }}</span>
                                @endif
                            </div>

                            @if($task->description)
                                <p class="text-[11px] text-slate-500 line-clamp-1 mb-1">{{ $task->description }}</p>
                            @endif

                            <div class="flex items-center justify-between text-[10px] text-slate-400 font-medium">
                                <span>Assigned by: {{ $task->assigner?->name ?? 'Supervisor' }}</span>
                                @if($task->due_date)
                                    <span class="font-semibold text-slate-600 font-mono">
                                        Due: {{ \Illuminate\Support\Carbon::parse($task->due_date)->format('M d') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 px-4 text-center rounded-xl bg-slate-50/50 border border-dashed border-slate-200">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 mb-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </span>
                        <div class="text-xs font-bold text-slate-700">All caught up!</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">No pending duties in this view.</div>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Footer link --}}
        <div class="pt-3 border-t border-slate-100 mt-4 flex items-center justify-between">
            <span class="text-[11px] text-slate-400">Daily Operations</span>
            <a href="{{ route('filament.employee.pages.my-tasks-page') }}" class="text-xs font-bold text-slate-700 hover:text-slate-900 hover:underline">
                View All Tasks &rarr;
            </a>
        </div>
    </div>
</x-filament-widgets::widget>
